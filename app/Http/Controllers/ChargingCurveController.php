<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ChargingCurveController extends Controller
{
    private const CURVE_DIR = 'data/charging-curves';

    public function index(): View
    {
        $curves = $this->availableCurves();

        $slug = request('modele');
        $curve = $curves->firstWhere('slug', $slug) ?? $curves->first();

        if ($curve) {
            $curve = $this->withDerivedColumns($curve);
        }

        return view('charging_curves.index', [
            'curves' => $curves,
            'curve' => $curve,
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

    /**
     * Charge toutes les courbes disponibles depuis resources/data/charging-curves.
     * Les courbes sont des données de référence externes (evkx.net), pas des
     * données utilisateur : un fichier JSON par modèle suffit, pas de table.
     */
    private function availableCurves()
    {
        $dir = resource_path(self::CURVE_DIR);

        if (! File::isDirectory($dir)) {
            return collect();
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true))
            ->filter()
            ->sortBy('name')
            ->values();
    }
}
