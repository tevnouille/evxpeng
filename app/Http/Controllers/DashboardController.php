<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use App\Services\FuelPriceService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(FuelPriceService $fuelPriceService, ChargingCurveRepository $curves): View
    {
        $currentYear = (int) now()->year;

        $years = ChargingSession::selectRaw('DISTINCT YEAR(session_date) as annee')
            ->orderByDesc('annee')
            ->pluck('annee')
            ->map(fn ($year) => (int) $year)
            ->values();

        if (! $years->contains($currentYear)) {
            $years = $years->push($currentYear)->sortDesc()->values();
        }

        return view('dashboard.index', [
            'vehicles' => Vehicle::orderBy('name')->get(),
            'years' => $years,
            'currentYear' => $currentYear,
            // Toujours sur l'ensemble du compte, independamment des filtres
            // annee/vehicule du reste du tableau de bord : "depuis le debut"
            // n'a pas a etre redecouvert en choisissant "Toutes les annees".
            'lifetime' => $this->lifetime($fuelPriceService, $curves),
            'showFuelEquivalent' => (bool) (CurrentUser::get()?->show_fuel_equivalent ?? true),
        ]);
    }

    /**
     * Cumul toutes recharges confondues, depuis la premiere enregistree.
     *
     * @return array<string, mixed>
     */
    private function lifetime(FuelPriceService $fuelPriceService, ChargingCurveRepository $curves): array
    {
        $sessions = ChargingSession::with('vehicle')->get();

        $totalKwh = (float) $sessions->sum('quantity_kwh');
        $totalCost = (float) $sessions->sum('total_cost');
        $totalRealCost = (float) $sessions->sum('real_cost');

        // Litteralement les kWh de recharges facturees a 0 € : pas une part
        // proportionnelle du gain (qui melangerait remises partielles et
        // recharges vraiment gratuites), juste ce qui n'a rien coute.
        $unpaidKwh = (float) $sessions
            ->filter(fn ($session) => (float) $session->total_cost === 0.0)
            ->sum('quantity_kwh');

        // "Un plein" se rapporte au vehicule du compte, pas a une capacite
        // inventee : celui par defaut, ou le seul s'il n'y en a qu'un.
        $referenceVehicle = Vehicle::where('is_default', true)->first() ?? Vehicle::orderBy('name')->first();
        $tankKwh = $referenceVehicle ? ($curves->find($referenceVehicle->charging_curve)['battery_net_kwh'] ?? null) : null;

        // Meme regle que partout ailleurs (App\Services\FuelPriceService) :
        // prix du jour de chaque recharge, consommation du vehicule de chaque
        // recharge, aucune valeur inventee pour un vehicule sans consommation
        // renseignee — il reste compte dans total_sessions, pas dans configured_sessions.
        $equivalenceRows = $sessions->map(fn ($session) => (object) [
            'session_date' => $session->session_date,
            'quantity_kwh' => (float) $session->quantity_kwh,
            'kwh_per_100km' => $session->vehicle->kwh_per_100km,
            'essence_l_per_100km' => $session->vehicle->essence_l_per_100km,
            'diesel_l_per_100km' => $session->vehicle->diesel_l_per_100km,
        ]);
        $fuelEquivalent = $fuelPriceService->equivalentTotals($equivalenceRows);

        return [
            'kwh' => round($totalKwh, 1),
            'cost' => round($totalCost, 2),
            'real_cost' => round($totalRealCost, 2),
            'gain' => round($totalCost - $totalRealCost, 2),
            'unpaid_kwh' => round($unpaidKwh, 1),
            'tank_kwh' => $tankKwh,
            'tank_count' => $tankKwh ? round($unpaidKwh / (float) $tankKwh, 1) : null,
            'sessions_count' => $sessions->count(),
            'first_date' => $sessions->min('session_date'),
            'essence_liters' => $fuelEquivalent['essence_liters'],
            'diesel_liters' => $fuelEquivalent['diesel_liters'],
            'essence_cost' => $fuelEquivalent['essence_cost'],
            'diesel_cost' => $fuelEquivalent['diesel_cost'],
            'savings_essence' => round($fuelEquivalent['essence_cost'] - $totalCost, 2),
            'savings_diesel' => round($fuelEquivalent['diesel_cost'] - $totalCost, 2),
            'configured_sessions' => $fuelEquivalent['configured_sessions'],
            'total_sessions' => $fuelEquivalent['total_sessions'],
            'estimated' => $fuelEquivalent['estimated'],
        ];
    }

    public function data(Request $request, FuelPriceService $fuelPriceService): JsonResponse
    {
        $fuelPriceService->ensureToday();

        $granularity = $request->query('granularity', 'month');
        $vehicleId = $request->query('vehicle_id');
        $year = $request->query('year');

        $periodExpr = match ($granularity) {
            'day' => "DATE_FORMAT(session_date, '%Y-%m-%d')",
            'week' => "CONCAT(YEAR(session_date), '-S', LPAD(WEEK(session_date, 3), 2, '0'))",
            'year' => 'YEAR(session_date)',
            default => "DATE_FORMAT(session_date, '%Y-%m')",
        };

        $rows = ChargingSession::selectRaw("$periodExpr as period, MIN(session_date) as period_start, SUM(quantity_kwh) as kwh, SUM(total_cost) as cost, SUM(real_cost) as real_cost, COUNT(*) as sessions_count")
            ->when($vehicleId, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($year, fn ($query) => $query->whereYear('session_date', $year))
            ->groupBy('period')
            ->orderBy('period_start')
            ->get();

        $byProvider = ChargingSession::query()
            ->join('providers', 'providers.id', '=', 'charging_sessions.provider_id')
            ->when($vehicleId, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($year, fn ($query) => $query->whereYear('charging_sessions.session_date', $year))
            ->selectRaw('providers.name as name, COUNT(*) as sessions_count, SUM(quantity_kwh) as kwh, '
                .'SUM(total_cost) as cost, SUM(real_cost) as real_cost')
            ->groupBy('providers.id', 'providers.name')
            ->orderByDesc('kwh')
            ->get();

        // Equivalent carburant calcule au niveau de chaque recharge individuelle :
        // prix du jour de LA recharge (pas une date de bucket approximative) et
        // consommation propre au VEHICULE de cette recharge (jointure, pas de valeur
        // par defaut : un vehicule sans consommation renseignee n'est simplement pas
        // compte dans l'equivalent, voir FuelPriceService::equivalentByGroup).
        $sessionRows = ChargingSession::query()
            ->join('vehicles', 'vehicles.id', '=', 'charging_sessions.vehicle_id')
            ->when($vehicleId, fn ($query) => $query->where('charging_sessions.vehicle_id', $vehicleId))
            ->when($year, fn ($query) => $query->whereYear('charging_sessions.session_date', $year))
            ->selectRaw(
                "$periodExpr as period, charging_sessions.session_date, charging_sessions.quantity_kwh, " .
                'vehicles.kwh_per_100km, vehicles.essence_l_per_100km, vehicles.diesel_l_per_100km'
            )
            ->get();

        $equivalentByPeriod = $fuelPriceService->equivalentByGroup($sessionRows, fn ($row) => $row->period);

        $totalEssenceLiters = array_sum(array_column($equivalentByPeriod, 'essence_liters'));
        $totalDieselLiters = array_sum(array_column($equivalentByPeriod, 'diesel_liters'));
        $totalEssenceCost = array_sum(array_column($equivalentByPeriod, 'essence_cost'));
        $totalDieselCost = array_sum(array_column($equivalentByPeriod, 'diesel_cost'));
        $knownPriceSessions = array_sum(array_column($equivalentByPeriod, 'known'));
        $configuredSessions = array_sum(array_column($equivalentByPeriod, 'configured'));
        $totalSessions = array_sum(array_column($equivalentByPeriod, 'count'));
        $totalElectricCost = (float) $rows->sum('cost');

        return response()->json([
            'granularity' => $granularity,
            'labels' => $rows->pluck('period')->values(),
            'kwh' => $rows->pluck('kwh')->map(fn ($v) => (float) $v)->values(),
            'cost' => $rows->pluck('cost')->map(fn ($v) => (float) $v)->values(),
            'real_cost' => $rows->pluck('real_cost')->map(fn ($v) => (float) $v)->values(),
            // Gain = ce qui a ete facture moins ce que la recharge valait :
            // negatif quand on a paye moins que sa valeur (recharge offerte).
            'gain' => $rows->map(fn ($r) => round((float) $r->cost - (float) $r->real_cost, 2))->values(),
            'avg_cost_per_kwh' => $rows->map(fn ($r) => $r->kwh > 0 ? round($r->cost / $r->kwh, 4) : 0)->values(),
            'sessions_count' => $rows->pluck('sessions_count')->map(fn ($v) => (int) $v)->values(),
            'fuel_equivalent_essence_cost' => $rows->map(fn ($r) => round($equivalentByPeriod[$r->period]['essence_cost'] ?? 0, 2))->values(),
            'fuel_equivalent_diesel_cost' => $rows->map(fn ($r) => round($equivalentByPeriod[$r->period]['diesel_cost'] ?? 0, 2))->values(),
            'fuel_equivalent' => [
                'essence_liters' => round($totalEssenceLiters, 2),
                'diesel_liters' => round($totalDieselLiters, 2),
                'essence_cost' => round($totalEssenceCost, 2),
                'diesel_cost' => round($totalDieselCost, 2),
                'avg_essence_price' => $totalEssenceLiters > 0 ? round($totalEssenceCost / $totalEssenceLiters, 3) : null,
                'avg_diesel_price' => $totalDieselLiters > 0 ? round($totalDieselCost / $totalDieselLiters, 3) : null,
                'savings_essence' => round($totalEssenceCost - $totalElectricCost, 2),
                'savings_diesel' => round($totalDieselCost - $totalElectricCost, 2),
                'known_price_sessions' => $knownPriceSessions,
                'configured_sessions' => $configuredSessions,
                'total_sessions' => $totalSessions,
                'estimated' => $totalSessions > 0 && $knownPriceSessions < $totalSessions,
            ],
            'by_provider' => $byProvider->map(fn ($p) => [
                'name' => $p->name,
                'sessions_count' => (int) $p->sessions_count,
                'kwh' => (float) $p->kwh,
                'cost' => (float) $p->cost,
                'avg_cost_per_kwh' => $p->kwh > 0 ? round($p->cost / $p->kwh, 4) : null,
                // Part calculee sur le cout total de la periode filtree, pas sur
                // la somme des fournisseurs : une recharge sans fournisseur
                // renseigne compte dans le total et doit manquer a la somme des
                // parts, plutot que d'etre diluee dans les autres.
                'cost_share' => $totalElectricCost > 0 ? round($p->cost / $totalElectricCost * 100, 1) : null,
                // Ce que la recharge valait moins ce qui a ete debite : negatif
                // quand on a paye plus que la valeur, positif quand on a
                // economise. Meme convention que la vignette Gain.
                'gain' => round((float) $p->cost - (float) $p->real_cost, 2),
            ])->values(),
        ]);
    }
}
