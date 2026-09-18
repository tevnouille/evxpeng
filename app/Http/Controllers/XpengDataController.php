<?php

namespace App\Http\Controllers;

use App\Models\XpengDataExport;
use App\Models\XpengTelemetry;
use App\Services\XpengChargeDetector;
use App\Services\XpengExportParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
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
    /**
     * Au-dela, une courbe fige le navigateur sans rien montrer de plus (meme
     * constat que ObdReadings::MAX_POINTS pour l'onglet Statistiques OBD).
     */
    private const MAX_POINTS = 600;

    /**
     * Fenetre affichee par defaut. Les releves plus anciens ne sont jamais
     * purges (meme regle que vehicle_telemetries, CLAUDE.md) mais rester sur
     * "toute la table" chargerait une collection qui grossit chaque jour,
     * pour une page qui n'a pas de navigation par periode -- a ajouter le
     * jour ou regarder plus loin que 30 jours devient un vrai besoin.
     */
    private const JOURS_AFFICHES = 30;

    public function index(XpengChargeDetector $detecteur): View
    {
        $exports = XpengDataExport::orderByDesc('requested_at')->limit(30)->get();

        $releves = XpengTelemetry::where('horodatage', '>=', now()->subDays(self::JOURS_AFFICHES))
            ->orderBy('horodatage')
            ->get();

        return view('my_vehicle.xpeng_data', [
            'dernier' => $exports->first(),
            'exports' => $exports,
            'releves' => $releves,
            'mesures' => $releves->isEmpty() ? [] : $this->mesures($releves),
            'recharges' => $releves->isEmpty() ? [] : $detecteur->detecter($releves),
        ]);
    }

    /**
     * Depot manuel d'un export deja telecharge sur le portail Xpeng --
     * en attendant que xpeng:sync (appId/appSecret) prenne le relais, ou
     * pour completer une periode que l'automatisation n'aurait pas couverte.
     *
     * Traite en synchrone (pas de file d'attente sur ce projet) : le plus
     * gros import vu a ce jour (un mois complet) prend environ deux minutes,
     * d'ou les delais augmentes cote nginx (docker/nginx/default.conf et le
     * vhost de l'hote) plutot qu'une reponse coupee en cours de route.
     */
    public function importer(Request $request, XpengExportParser $parser): RedirectResponse
    {
        $request->validate([
            // 100 Mo : le plus gros export vu (30 jours) pesait 15 Mo compresses.
            'fichier' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ]);

        $nom = now()->format('Y-m-d_His').'_'.$request->file('fichier')->getClientOriginalName();
        $dossier = storage_path('app/xpeng/imports');

        if (! is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }

        // Deplacement direct plutot que Storage::disk('local') : ce disque a
        // pour racine storage/app/private/ depuis Laravel 11, alors que
        // XpengExportParser/SyncXpengData travaillent en chemins relatifs a
        // storage/app/ tout court (chemin_fichier en base). Utiliser le
        // disque ici aurait ecrit au bon endroit pour lui, au mauvais pour
        // tout le reste de cette fonctionnalite.
        $request->file('fichier')->move($dossier, $nom);
        $chemin = 'xpeng/imports/'.$nom;

        try {
            $nombre = $parser->importZip(storage_path('app/'.$chemin));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', "Le fichier a bien ete recu, mais l'import a echoue : {$e->getMessage()}");
        }

        return back()->with('success', "$nombre minute(s) importee(s)/mises a jour depuis $nom.");
    }

    /**
     * @return array<int, array{label: string, unit: ?string, labels: array<int, string>, values: array<int, float>}>
     */
    private function mesures(Collection $releves): array
    {
        $definitions = [
            ['champ' => 'soc_moy', 'label' => 'Batterie (SoC)', 'unite' => '%'],
            ['champ' => 'autonomie_km', 'label' => 'Autonomie estimée', 'unite' => 'km'],
            ['champ' => 'odometre_km', 'label' => 'Kilométrage', 'unite' => 'km'],
            ['champ' => 'vitesse_max_kmh', 'label' => 'Vitesse (max/minute)', 'unite' => 'km/h'],
            ['champ' => 'puissance_charge_moy_kw', 'label' => 'Puissance de charge', 'unite' => 'kW'],
            ['champ' => 'temp_batterie_max_c', 'label' => 'Température batterie (max)', 'unite' => '°C'],
            // Le numero de la zone la plus chaude/froide, pas sa temperature
            // (deja ci-dessus) : utile pour reperer une zone durablement
            // desequilibree plutot qu'un pic ponctuel.
            ['champ' => 'cellule_temp_max_num', 'label' => 'Zone la plus chaude (n°)', 'unite' => null, 'decimales' => 0],
            ['champ' => 'cellule_temp_min_num', 'label' => 'Zone la plus froide (n°)', 'unite' => null, 'decimales' => 0],
            // Stockees en kPa (unite brute de l'export), affichees en bar :
            // c'est l'unite lue sur un manometre de pneu, kPa ne s'y compare pas.
            ['champ' => 'pression_av_gauche_kpa', 'label' => 'Pression avant gauche', 'unite' => 'bar', 'facteur' => 0.01, 'decimales' => 2],
            ['champ' => 'pression_av_droite_kpa', 'label' => 'Pression avant droite', 'unite' => 'bar', 'facteur' => 0.01, 'decimales' => 2],
            ['champ' => 'pression_ar_gauche_kpa', 'label' => 'Pression arrière gauche', 'unite' => 'bar', 'facteur' => 0.01, 'decimales' => 2],
            ['champ' => 'pression_ar_droite_kpa', 'label' => 'Pression arrière droite', 'unite' => 'bar', 'facteur' => 0.01, 'decimales' => 2],
        ];

        $mesures = [];

        foreach ($definitions as $definition) {
            $points = $releves
                ->filter(fn (XpengTelemetry $r) => $r->{$definition['champ']} !== null)
                ->values();

            if ($points->isEmpty()) {
                continue;
            }

            $echantillon = $this->echantillonner($points);
            $facteur = $definition['facteur'] ?? 1;
            $decimales = $definition['decimales'] ?? 1;

            $mesures[] = [
                'label' => $definition['label'],
                'unit' => $definition['unite'],
                'count' => $points->count(),
                'labels' => $echantillon->map(fn (XpengTelemetry $r) => $r->horodatage->format('d/m H:i'))->all(),
                'values' => $echantillon->map(fn (XpengTelemetry $r) => round((float) $r->{$definition['champ']} * $facteur, $decimales))->all(),
            ];
        }

        return $mesures;
    }

    /** @return Collection<int, XpengTelemetry> */
    private function echantillonner(Collection $points): Collection
    {
        $total = $points->count();

        if ($total <= self::MAX_POINTS) {
            return $points;
        }

        $pas = (int) ceil($total / self::MAX_POINTS);
        $garde = $points->filter(fn ($p, $i) => $i % $pas === 0)->values();

        // Le dernier point est conserve d'office, sinon la courbe s'arrete
        // avant la fin de la periode disponible.
        return $garde->push($points->last());
    }

    public function telecharger(XpengDataExport $export): Response
    {
        abort_unless($export->chemin_fichier, 404);

        $chemin = storage_path('app/'.$export->chemin_fichier);

        abort_unless(is_file($chemin), 404);

        return response()->download($chemin);
    }
}
