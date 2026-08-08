<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Services\FuelPriceService;
use Illuminate\Http\Request;
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

        $countsByMonth = ChargingSession::selectRaw('MONTH(session_date) as mois, COUNT(*) as total')
            ->whereYear('session_date', $year)
            ->groupBy('mois')
            ->pluck('total', 'mois');

        return view('history.index', [
            'years' => $years,
            'year' => $year,
            'months' => self::MOIS_FR,
            'countsByMonth' => $countsByMonth,
        ]);
    }

    public function show(int $year, int $month, FuelPriceService $fuelPriceService): View
    {
        $fuelPriceService->ensureToday();

        $sessions = ChargingSession::with(['vehicle', 'location', 'provider', 'powerRating'])
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        $totalKwh = (float) $sessions->sum('quantity_kwh');
        $totalCost = (float) $sessions->sum('total_cost');

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
            'stats' => [
                'kwh' => $totalKwh,
                'cost' => $totalCost,
                'avg_cost_per_kwh' => $totalKwh > 0 ? $totalCost / $totalKwh : null,
                'sessions_count' => $sessions->count(),
            ],
            'fuelEquivalent' => [
                'essence_liters' => $fuelEquivalent['essence_liters'],
                'diesel_liters' => $fuelEquivalent['diesel_liters'],
                'essence_cost' => $fuelEquivalent['essence_cost'],
                'diesel_cost' => $fuelEquivalent['diesel_cost'],
                'avg_essence_price' => $fuelEquivalent['essence_liters'] > 0 ? round($fuelEquivalent['essence_cost'] / $fuelEquivalent['essence_liters'], 3) : null,
                'avg_diesel_price' => $fuelEquivalent['diesel_liters'] > 0 ? round($fuelEquivalent['diesel_cost'] / $fuelEquivalent['diesel_liters'], 3) : null,
                'savings_essence' => round($fuelEquivalent['essence_cost'] - $totalCost, 2),
                'savings_diesel' => round($fuelEquivalent['diesel_cost'] - $totalCost, 2),
                'known_price_sessions' => $fuelEquivalent['known_price_sessions'],
                'total_sessions' => $fuelEquivalent['total_sessions'],
                'estimated' => $fuelEquivalent['estimated'],
            ],
        ]);
    }
}
