<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Vehicle;
use App\Services\FuelPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $years = ChargingSession::selectRaw('DISTINCT YEAR(session_date) as annee')
            ->orderByDesc('annee')
            ->pluck('annee')
            ->map(fn ($year) => (int) $year)
            ->values();

        return view('dashboard.index', [
            'vehicles' => Vehicle::orderBy('name')->get(),
            'years' => $years,
        ]);
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

        $rows = ChargingSession::selectRaw("$periodExpr as period, MIN(session_date) as period_start, SUM(quantity_kwh) as kwh, SUM(total_cost) as cost, COUNT(*) as sessions_count")
            ->when($vehicleId, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($year, fn ($query) => $query->whereYear('session_date', $year))
            ->groupBy('period')
            ->orderBy('period_start')
            ->get();

        $byProvider = ChargingSession::query()
            ->join('providers', 'providers.id', '=', 'charging_sessions.provider_id')
            ->when($vehicleId, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($year, fn ($query) => $query->whereYear('charging_sessions.session_date', $year))
            ->selectRaw('providers.name as name, SUM(quantity_kwh) as kwh, SUM(total_cost) as cost')
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
                'kwh' => (float) $p->kwh,
                'cost' => (float) $p->cost,
            ])->values(),
        ]);
    }
}
