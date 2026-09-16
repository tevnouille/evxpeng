<?php

namespace App\Http\Controllers;

use App\Models\XpengDataExport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Suivi de l'integration Xpeng (donnees constructeur, hors OBD/MQTT).
 *
 * L'API Xpeng ne renvoie pas de telemetrie en direct : un fichier d'export
 * est recupere par la commande planifiee `xpeng:sync` (routes/console.php).
 * Cette page se contente donc d'afficher ce que la derniere synchronisation
 * a rapporte -- jamais d'appel a l'API depuis une requete HTTP entrante, le
 * quota (5 soumissions/24h) est trop serre pour le risquer sur une simple
 * visite de page.
 */
class XpengDataController extends Controller
{
    public function index(): View
    {
        $exports = XpengDataExport::orderByDesc('requested_at')->limit(30)->get();

        return view('my_vehicle.xpeng_data', [
            'dernier' => $exports->first(),
            'exports' => $exports,
        ]);
    }

    public function telecharger(XpengDataExport $export): Response
    {
        abort_unless($export->chemin_fichier, 404);
        abort_unless(Storage::disk('local')->exists($export->chemin_fichier), 404);

        return Storage::disk('local')->download($export->chemin_fichier);
    }
}
