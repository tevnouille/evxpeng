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

        return view('charging_curves.index', [
            'curves' => $curves,
            'curve' => $curve,
        ]);
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
