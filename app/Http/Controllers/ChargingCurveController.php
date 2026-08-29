<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingCurveController extends Controller
{
    public function __construct(private readonly ChargingCurveRepository $curves)
    {
    }

    public function index(Request $request): View
    {
        // Seuls les vehicules auxquels une courbe a ete associee dans
        // /admin/vehicules sont proposes.
        $vehicles = Vehicle::whereNotNull('charging_curve')
            ->with('latestTelemetry')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->filter(fn ($vehicle) => $this->curves->find($vehicle->charging_curve) !== null)
            ->values();

        $requested = $request->query('vehicule');

        // A defaut de choix explicite, le vehicule par defaut (orderByDesc ci-dessus
        // le place en tete).
        $vehicle = $vehicles->firstWhere('id', (int) $requested) ?? $vehicles->first();

        $curve = $vehicle ? $this->curves->find($vehicle->charging_curve) : null;

        if ($curve) {
            $curve = $this->withDerivedColumns($curve);
        }

        // Niveau de charge remonte par ABRP, arrondi au point de courbe le plus
        // proche : la courbe est echantillonnee au pourcent entier.
        $telemetry = $vehicle?->latestTelemetry;
        $currentSoc = null;
        $currentPoint = null;

        if ($curve && $telemetry && $telemetry->soc !== null) {
            $currentSoc = (int) round((float) $telemetry->soc);
            $currentPoint = collect($curve['points'])->firstWhere('soc', $currentSoc);
        }

        return view('charging_curves.index', [
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'curve' => $curve,
            'telemetry' => $telemetry,
            'currentSoc' => $currentSoc,
            'currentPoint' => $currentPoint,
        ]);
    }

    /**
     * Ajoute a chaque point : l'energie presente dans la batterie a ce niveau
     * de charge, et le temps restant pour atteindre 80 / 90 / 100 %.
     */
    private function withDerivedColumns(array $curve): array
    {
        $points = $curve['points'];
        $targets = [80, 90, 100];

        $secondsBySoc = [];
        foreach ($points as $point) {
            $secondsBySoc[$point['soc']] = $this->toSeconds($point['time']);
        }

        foreach ($points as $index => $point) {
            // Capacite nette non calculee ici : elle serait identique a la colonne
            // "Energie chargee" de la courbe (evkx compte l'energie sur la capacite utile).
            $points[$index]['battery_gross_kwh'] = round($point['soc'] * $curve['battery_kwh'] / 100, 1);

            foreach ($targets as $target) {
                // Une cible deja atteinte (ou absente de la courbe) n'a pas de temps restant.
                $reached = $point['soc'] >= $target || ! isset($secondsBySoc[$target]);
                $remaining = $reached ? null : $secondsBySoc[$target] - $secondsBySoc[$point['soc']];

                $points[$index]['to_'.$target] = $reached ? null : $this->formatDuration($remaining);
                // Version numerique (minutes) pour le graphique.
                $points[$index]['to_'.$target.'_minutes'] = $reached ? null : round($remaining / 60, 2);
            }
        }

        $curve['points'] = $points;

        return $curve;
    }

    private function toSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(array_map('intval', explode(':', $time)), 3, 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
