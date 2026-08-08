<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
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

    public function show(int $year, int $month): View
    {
        $sessions = ChargingSession::with(['vehicle', 'location', 'provider', 'powerRating'])
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        return view('history.show', [
            'sessions' => $sessions,
            'year' => $year,
            'month' => $month,
            'monthName' => self::MOIS_FR[$month] ?? $month,
        ]);
    }
}
