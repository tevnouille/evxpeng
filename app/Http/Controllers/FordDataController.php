<?php

namespace App\Http\Controllers;

use App\Models\FordOAuthToken;
use App\Models\FordTelemetry;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Suivi de l'integration FordConnect (donnees constructeur, hors OBD/MQTT).
 *
 * Meme principe que XpengDataController : cette page affiche ce que la
 * derniere synchronisation (`ford:sync`) a rapporte, jamais d'appel a
 * l'API sur une simple visite -- seulement sur le bouton explicite de
 * synchroniser().
 */
class FordDataController extends Controller
{
    /** Meme motif que XpengDataController::MAX_POINTS. */
    private const MAX_POINTS = 600;

    /** Meme motif que XpengDataController::JOURS_AFFICHES. */
    private const JOURS_AFFICHES = 30;

    /**
     * Au-dela, une valeur est signalee comme ancienne. Deux passages de
     * ford:sync (toutes les 30 min) sans rien de neuf : la voiture ne
     * remonte plus rien, en general parce qu'elle est a l'arret -- Ford
     * renvoie alors indefiniment le dernier releve connu (une temperature
     * de 8h lue a 22h a deja ete prise pour la valeur du moment).
     */
    private const ANCIENNETE_MINUTES = 60;

    /** Colonne affichee => metrique brute Ford, qui porte son propre updateTime. */
    private const METRIQUES_TUILES = [
        'soc' => 'batteryStateOfCharge',
        'odometre_km' => 'odometer',
        'ignition_status' => 'ignitionStatus',
        'outside_temp_c' => 'outsideTemperature',
    ];

    public function index(): View
    {
        $releves = FordTelemetry::where('recorded_at', '>=', now()->subDays(self::JOURS_AFFICHES))
            ->orderBy('recorded_at')
            ->get();

        $dernier = $releves->last();

        return view('my_vehicle.ford_data', [
            'isAdmin' => (bool) CurrentUser::get()?->is_admin,
            'ancienneteMinutes' => self::ANCIENNETE_MINUTES,
            'releveAncien' => $dernier !== null && $dernier->recorded_at->lt(now()->subMinutes(self::ANCIENNETE_MINUTES)),
            'valeursAnciennes' => $dernier !== null ? $this->valeursAnciennes($dernier) : [],
            // Simple lecture : le cache est rempli par ford:sync, jamais ici
            // (voir son commentaire).
            'imageUrl' => Cache::get('ford_vehicle_image_url'),
            'dernierJeton' => FordOAuthToken::where('vin', config('services.ford.vin'))->first(),
            'dernier' => $dernier,
            'releves' => $releves,
            'mesures' => $releves->isEmpty() ? [] : $this->mesures($releves),
        ]);
    }

    /**
     * Horodatage propre a chaque valeur des tuiles, seulement quand il est
     * ancien. Chaque metrique Ford porte son updateTime, parfois bien plus
     * vieux que l'updateTime global du releve (le SoC peut dater d'une heure
     * avant le reste) : l'age du releve seul ne suffit pas a juger d'une
     * valeur.
     *
     * @return array<string, \Illuminate\Support\Carbon>
     */
    private function valeursAnciennes(FordTelemetry $releve): array
    {
        $seuil = now()->subMinutes(self::ANCIENNETE_MINUTES);
        $anciennes = [];

        foreach (self::METRIQUES_TUILES as $colonne => $metrique) {
            $horodatage = $releve->metrics[$metrique]['updateTime'] ?? null;

            if ($horodatage === null) {
                continue;
            }

            // updateTime est en UTC (suffixe Z), comme l'updateTime global
            // deja converti par ford:sync.
            $moment = \Illuminate\Support\Carbon::parse($horodatage)->setTimezone(config('app.timezone'));

            if ($moment->lt($seuil)) {
                $anciennes[$colonne] = $moment;
            }
        }

        return $anciennes;
    }

    /**
     * Lance `ford:sync` a la demande. Directement dans la requete,
     * contrairement a Xpeng : un seul GET, quelques secondes.
     *
     * Dit explicitement quand Ford a renvoye le meme releve qu'avant : la
     * voiture ne remonte rien tant qu'elle est a l'arret, et un « releve
     * enregistre » seul laissait croire a une donnee fraiche (confusion deja
     * rencontree sur la temperature exterieure).
     */
    public function synchroniser(): RedirectResponse
    {
        $avant = FordTelemetry::latest('id')->value('recorded_at');

        $code = Artisan::call('ford:sync');
        $sortie = trim(Artisan::output());

        if ($code !== 0) {
            return back()->with('error', $sortie !== '' ? $sortie : 'La synchronisation Ford a échoué.');
        }

        $apres = FordTelemetry::latest('id')->first()?->recorded_at;

        if ($apres === null) {
            return back()->with('success', 'Synchronisation Ford terminée.');
        }

        $horodatage = $apres->timezone(config('app.timezone'))->format('d/m/Y H:i');

        if ($avant !== null && $apres->equalTo($avant)) {
            return back()->with('success', "Rien de nouveau : Ford renvoie toujours le relevé du {$horodatage}. La voiture ne remonte rien tant qu'elle est à l'arrêt.");
        }

        return back()->with('success', "Nouveau relevé Ford récupéré : {$horodatage}.");
    }

    /**
     * @return array<int, array{label: string, unit: ?string, labels: array<int, string>, values: array<int, float>}>
     */
    private function mesures(Collection $releves): array
    {
        $definitions = [
            ['champ' => 'soc', 'label' => 'Batterie (SoC)', 'unite' => '%'],
            ['champ' => 'odometre_km', 'label' => 'Kilométrage', 'unite' => 'km'],
            ['champ' => 'battery_voltage', 'label' => 'Tension batterie 12V', 'unite' => 'V'],
            // Pas de « Température ambiante » (ambient_temp_c) : toujours 0 sur
            // cette voiture, y compris pendant un trajet ou outsideTemperature
            // variait (constate le 23/09/2026) -- champ non renseigne par le
            // vehicule. La colonne reste alimentee, au cas ou.
            ['champ' => 'outside_temp_c', 'label' => 'Température extérieure', 'unite' => '°C'],
        ];

        $mesures = [];

        foreach ($definitions as $definition) {
            $points = $releves
                ->filter(fn (FordTelemetry $r) => $r->{$definition['champ']} !== null)
                ->values();

            if ($points->isEmpty()) {
                continue;
            }

            $echantillon = $this->echantillonner($points);

            $mesures[] = [
                'label' => $definition['label'],
                'unit' => $definition['unite'],
                'count' => $points->count(),
                'labels' => $echantillon->map(fn (FordTelemetry $r) => $r->recorded_at->timezone(config('app.timezone'))->format('d/m H:i'))->all(),
                'values' => $echantillon->map(fn (FordTelemetry $r) => round((float) $r->{$definition['champ']}, 1))->all(),
            ];
        }

        return $mesures;
    }

    /** @return Collection<int, FordTelemetry> */
    private function echantillonner(Collection $points): Collection
    {
        $total = $points->count();

        if ($total <= self::MAX_POINTS) {
            return $points;
        }

        $pas = (int) ceil($total / self::MAX_POINTS);
        $garde = $points->filter(fn ($p, $i) => $i % $pas === 0)->values();

        return $garde->push($points->last());
    }
}
