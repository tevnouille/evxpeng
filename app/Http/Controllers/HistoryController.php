<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Services\FuelPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HistoryController extends Controller
{
    private const MOIS_FR = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    public function index(Request $request): View
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

        $year = (int) $request->query('year', $currentYear);

        $statsByMonth = ChargingSession::selectRaw('MONTH(session_date) as mois, COUNT(*) as total, SUM(quantity_kwh) as kwh, SUM(total_cost) as cost, SUM(real_cost) as real_cost')
            ->whereYear('session_date', $year)
            ->groupBy('mois')
            ->get()
            ->keyBy('mois')
            ->map(fn ($row) => [
                'count' => (int) $row->total,
                'kwh' => (float) $row->kwh,
                'cost' => (float) $row->cost,
                'real_cost' => (float) $row->real_cost,
                'gain' => round((float) $row->real_cost - (float) $row->cost, 2),
            ]);

        return view('history.index', [
            'years' => $years,
            'year' => $year,
            'months' => self::MOIS_FR,
            'statsByMonth' => $statsByMonth,
        ]);
    }

    public function show(int $year, int $month, FuelPriceService $fuelPriceService): View
    {
        $fuelPriceService->ensureToday();

        $previous = $month === 1 ? ['year' => $year - 1, 'month' => 12] : ['year' => $year, 'month' => $month - 1];
        $next = $month === 12 ? ['year' => $year + 1, 'month' => 1] : ['year' => $year, 'month' => $month + 1];

        $sessions = ChargingSession::with(['vehicle', 'location', 'provider', 'powerRating'])
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        $totalKwh = (float) $sessions->sum('quantity_kwh');
        $totalCost = (float) $sessions->sum('total_cost');
        $totalRealCost = (float) $sessions->sum('real_cost');

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $sessionsByDay = $sessions->groupBy(fn ($s) => $s->session_date->day);
        $dailyLabels = [];
        $dailyKwh = [];
        $dailyCost = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $daySessions = $sessionsByDay->get($day, collect());
            $dailyLabels[] = $day;
            $dailyKwh[] = round((float) $daySessions->sum('quantity_kwh'), 2);
            $dailyCost[] = round((float) $daySessions->sum('total_cost'), 2);
        }

        // Le provider est deja charge via with() : on regroupe en memoire plutot que
        // de relancer une requete agregee.
        $statsByProvider = $sessions
            ->groupBy(fn ($session) => $session->provider?->name ?? 'Inconnu')
            ->map(function ($providerSessions) use ($totalCost) {
                $kwh = (float) $providerSessions->sum('quantity_kwh');
                $cost = (float) $providerSessions->sum('total_cost');

                return [
                    'count' => $providerSessions->count(),
                    'kwh' => $kwh,
                    'cost' => $cost,
                    'avg_cost_per_kwh' => $kwh > 0 ? $cost / $kwh : null,
                    'cost_share' => $totalCost > 0 ? $cost / $totalCost * 100 : null,
                ];
            })
            ->sortByDesc('cost');

        $equivalenceRows = $sessions->map(fn ($session) => (object) [
            'session_date' => $session->session_date,
            'quantity_kwh' => (float) $session->quantity_kwh,
            'kwh_per_100km' => $session->vehicle->kwh_per_100km,
            'essence_l_per_100km' => $session->vehicle->essence_l_per_100km,
            'diesel_l_per_100km' => $session->vehicle->diesel_l_per_100km,
        ]);
        $fuelEquivalent = $fuelPriceService->equivalentTotals($equivalenceRows);

        return view('history.show', [
            'sessions' => $sessions,
            'year' => $year,
            'month' => $month,
            'monthName' => self::MOIS_FR[$month] ?? $month,
            'previous' => $previous,
            'next' => $next,
            'previousMonthName' => self::MOIS_FR[$previous['month']] ?? $previous['month'],
            'nextMonthName' => self::MOIS_FR[$next['month']] ?? $next['month'],
            'dailyLabels' => $dailyLabels,
            'dailyKwh' => $dailyKwh,
            'dailyCost' => $dailyCost,
            'statsByProvider' => $statsByProvider,
            'stats' => [
                'kwh' => $totalKwh,
                'cost' => $totalCost,
                'real_cost' => $totalRealCost,
                'gain' => round($totalRealCost - $totalCost, 2),
                'avg_cost_per_kwh' => $totalKwh > 0 ? $totalCost / $totalKwh : null,
                'sessions_count' => $sessions->count(),
            ],
            'fuelEquivalent' => [
                'km' => $fuelEquivalent['km'],
                'essence_liters' => $fuelEquivalent['essence_liters'],
                'diesel_liters' => $fuelEquivalent['diesel_liters'],
                'essence_cost' => $fuelEquivalent['essence_cost'],
                'diesel_cost' => $fuelEquivalent['diesel_cost'],
                'avg_essence_price' => $fuelEquivalent['essence_liters'] > 0 ? round($fuelEquivalent['essence_cost'] / $fuelEquivalent['essence_liters'], 3) : null,
                'avg_diesel_price' => $fuelEquivalent['diesel_liters'] > 0 ? round($fuelEquivalent['diesel_cost'] / $fuelEquivalent['diesel_liters'], 3) : null,
                'savings_essence' => round($fuelEquivalent['essence_cost'] - $totalCost, 2),
                'savings_diesel' => round($fuelEquivalent['diesel_cost'] - $totalCost, 2),
                'known_price_sessions' => $fuelEquivalent['known_price_sessions'],
                'configured_sessions' => $fuelEquivalent['configured_sessions'],
                'total_sessions' => $fuelEquivalent['total_sessions'],
                'estimated' => $fuelEquivalent['estimated'],
            ],
        ]);
    }
}
