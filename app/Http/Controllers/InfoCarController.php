<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\XpengTelemetry;
use App\Services\BatteryHealth;
use App\Services\ChargeCurveSimulator;
use App\Services\ChargingCurveRepository;
use App\Services\ReverseGeocoder;
use App\Services\SpeedLimitLookup;
use App\Services\VehicleState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Etat du vehicule, affiche sans authentification.
 *
 * Destine au navigateur de la voiture, ou une ceremonie passkey n'a pas sa
 * place. La page est donc lisible par quiconque connait l'adresse.
 *
 * La position y figure a la demande d'atran, qui en a pese la portee : elle est
 * publique comme le reste de la page. Le bouton ne la dissimule pas — les
 * coordonnees sont dans la source — il evite seulement d'envoyer la position a
 * OpenStreetMap a chaque affichage, comme le fait deja « Ma voiture ».
 *
 * Le vehicule n'est pas choisi d'apres un compte, puisqu'il n'y en a pas : on
 * prend celui par defaut, de maniere deterministe.
 *
 * Protegee par un code a 6 chiffres (config('services.info_car.pin')), retenu
 * un an par cookie — pas une identite, juste un filtre contre qui tomberait
 * sur l'adresse sans la connaitre. Le cookie est chiffre par le middleware
 * standard de Laravel (EncryptCookies, non exclu ici) : sa seule valeur en
 * clair pour qui l'intercepterait est celle, deja publique, de la page.
 */
class InfoCarController extends Controller
{
    /** Un an, en minutes : la duree demandee avant de redemander le code. */
    private const UNLOCK_MINUTES = 60 * 24 * 365;

    private const UNLOCK_COOKIE = 'infocar_code';

    public function __construct(
        private readonly VehicleState $state,
        private readonly ChargingCurveRepository $curves,
        private readonly ChargeCurveSimulator $simulator,
        private readonly ReverseGeocoder $geocoder,
        private readonly BatteryHealth $battery,
        private readonly SpeedLimitLookup $speedLimit,
    ) {
    }

    /** Puissances de borne proposees, en kW. */
    private const PUISSANCES = [3.6, 7.4, 11.0, 150.0, 300.0];

    /** Niveaux vises. */
    private const CIBLES = [80, 90, 100];

    /**
     * Serie temporelle de l'autonomie estimee, pour le graphique de l'onglet
     * « Courbe ». Aucun signal de charge separe n'est ajoute : une pente
     * montante en fin de courbe le dit deja aussi clairement qu'un badge.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\VehicleTelemetry>  $history
     * @return array<int, array{at: \Illuminate\Support\Carbon, km: int}>
     */
    private function rangeSeries(\Illuminate\Support\Collection $history, ?float $netCapacity, ?float $consumption): array
    {
        if (! $netCapacity || ! $consumption) {
            return [];
        }

        return $history
            ->filter(fn ($releve) => $releve->soc !== null)
            ->map(fn ($releve) => [
                'at' => $releve->recorded_at,
                'km' => (int) round((float) $releve->soc / 100 * $netCapacity / $consumption * 100),
            ])
            ->values()
            ->all();
    }

    /**
     * Coordonnees SVG pretes a l'emploi pour tracer la serie ci-dessus, sans
     * bibliotheque de graphique : la page reste une seule requete, condition
     * deja posee pour le reste d'« Info voiture ».
     *
     * @param  array<int, array{at: \Illuminate\Support\Carbon, km: int}>  $series
     * @return array<string, mixed>|null
     */
    private function rangeChart(array $series): ?array
    {
        if (count($series) < 2) {
            return null;
        }

        $width = 600;
        $height = 220;
        // Marges asymetriques : de la place a gauche pour les valeurs km, en
        // bas pour l'horodatage. Un simple sous-titre sous le graphique s'est
        // revele repousse hors ecran par le SVG (flex: 1 1 auto, qui prend
        // tout l'espace vertical disponible) sur l'ecran sans defilement de
        // la voiture — l'horodatage doit donc faire partie du dessin lui-meme.
        $margeHaut = 16;
        $margeBas = 32;
        $margeGauche = 44;
        $margeDroite = 10;

        $instants = array_map(fn ($point) => $point['at']->getTimestamp(), $series);
        $valeurs = array_column($series, 'km');

        $tempsMin = min($instants);
        // Jamais nulle : un seul instant sur toute la serie ne tracerait rien.
        $tempsEtendue = max(1, max($instants) - $tempsMin);

        $kmMin = min($valeurs);
        $kmMax = max($valeurs);
        // Autonomie parfaitement stable sur la fenetre : sans ce plancher, tous
        // les points tomberaient sur la meme ligne et la division par zero
        // renverrait NAN.
        $kmEtendue = max(1, $kmMax - $kmMin);

        $largeurTrace = $width - $margeGauche - $margeDroite;
        $hauteurTrace = $height - $margeHaut - $margeBas;

        $points = [];

        foreach ($series as $i => $point) {
            $x = $margeGauche + ($instants[$i] - $tempsMin) / $tempsEtendue * $largeurTrace;
            $y = $margeHaut + $hauteurTrace - ($point['km'] - $kmMin) / $kmEtendue * $hauteurTrace;
            $points[] = round($x, 1).','.round($y, 1);
        }

        return [
            'points' => implode(' ', $points),
            'width' => $width,
            'height' => $height,
            'km_min' => $kmMin,
            'km_max' => $kmMax,
            // Coordonnees des etiquettes, calculees ici plutot que devinees
            // dans le gabarit : la vue n'a pas a connaitre les marges.
            'km_max_y' => round($margeHaut + 4, 1),
            'km_min_y' => round($margeHaut + $hauteurTrace, 1),
            'label_x' => round($margeGauche, 1),
            'temps_y' => round($height - 8, 1),
            'temps_fin_x' => round($width - $margeDroite, 1),
            'debut' => $series[0]['at'],
            'fin' => end($series)['at'],
        ];
    }

    /**
     * Temps de charge depuis le niveau actuel, par puissance de borne.
     *
     * Le calcul est celui du planificateur et de la page « Courbe de recharge »
     * — un seul modele de bridage dans l'application, sans quoi deux formules
     * finiraient par diverger.
     *
     * @param  array<string, mixed>|null  $courbe
     * @return array<int, array<string, mixed>>
     */
    private function recharges(?array $courbe, ?float $soc): array
    {
        if ($courbe === null || $soc === null) {
            return [];
        }

        $lignes = [];

        foreach (self::PUISSANCES as $puissance) {
            $durees = [];

            foreach (self::CIBLES as $cible) {
                // Un niveau deja atteint ne se recharge pas : on le dit plutot
                // que d'afficher un zero qui se lirait comme « instantane ».
                $durees[$cible] = $soc >= $cible
                    ? null
                    : $this->simulator->duration($courbe, $soc, (float) $cible, $puissance);
            }

            $lignes[] = ['puissance' => $puissance, 'durees' => $durees];
        }

        return $lignes;
    }

    /**
     * Le PIN protege aussi bien la page que le flux de donnees consomme par
     * le graphique de batterie : sans ce doublon de controle, ce dernier
     * serait une porte laissee grande ouverte a cote de la premiere.
     */
    private function deverrouille(Request $request): bool
    {
        $pin = config('services.info_car.pin');

        return $pin === null || $request->cookie(self::UNLOCK_COOKIE) === (string) $pin;
    }

    /**
     * Dix dernieres communes distinctes traversees, la plus recente d'abord.
     *
     * Fenetre volontairement plus large que les 24h de `$history` : une
     * voiture qui reste garee plusieurs jours au meme endroit ne doit pas
     * amputer la liste, elle doit simplement remonter plus loin pour la
     * remplir. Parcours du plus recent au plus ancien ; deux releves
     * consecutifs dans la meme commune prolongent le meme segment plutot que
     * d'en ouvrir un nouveau — sans quoi la liste ne montrerait que des
     * variantes du meme lieu, jamais dix endroits differents.
     *
     * @return array<int, array{ville: string, vu_a: \Illuminate\Support\Carbon}>
     */
    private function dernieresVilles(?Vehicle $vehicle): array
    {
        if (! $vehicle) {
            return [];
        }

        $positions = $vehicle->telemetries()
            ->whereNotNull('lat')->whereNotNull('lon')
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderByDesc('recorded_at')
            // Borne dure contre un scan sans fin si la voiture n'a change de
            // commune qu'une poignee de fois sur tout le mois : la boucle
            // ci-dessous s'arrete de toute facon des dix segments trouves.
            ->limit(3000)
            ->get(['lat', 'lon', 'recorded_at']);

        $connues = $this->geocoder->known($positions);

        $villes = [];
        $courante = null;

        foreach ($positions as $point) {
            $cle = $this->geocoder->key((float) $point->lat, (float) $point->lon);
            $ville = $connues[$cle]->city ?? null;

            if ($ville === null || $ville === '') {
                // Position non geocodee (autoroute isolee, aire...) : on ne
                // clot pas le segment en cours pour autant, on passe au point
                // suivant.
                continue;
            }

            if ($ville === $courante) {
                continue;
            }

            $villes[] = ['ville' => $ville, 'vu_a' => $point->recorded_at];
            $courante = $ville;

            if (count($villes) >= 10) {
                break;
            }
        }

        return $villes;
    }

    /**
     * Consommation apparente (kWh/100 km) sur la fenetre glissante fournie,
     * a partir des seules baisses de SoC -- la charge est ignoree, elle
     * fausserait le ratio a cette granularite. Sous 20 km parcourus, le
     * ratio ne veut plus dire grand-chose, meme logique que
     * DailyVehicleActivity::MIN_KM_FOR_CONSUMPTION.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\VehicleTelemetry>  $history
     */
    private function consommationRecente(\Illuminate\Support\Collection $history, ?float $netCapacity): ?float
    {
        if (! $netCapacity) {
            return null;
        }

        $points = $history->whereNotNull('odometer')->whereNotNull('soc')->values();

        if ($points->count() < 2) {
            return null;
        }

        $km = 0.0;
        $socConsomme = 0.0;
        $precedent = null;

        foreach ($points as $point) {
            if ($precedent !== null) {
                $deltaOdo = (float) $point->odometer - (float) $precedent->odometer;

                // Meme garde-fou que DailyVehicleActivity::MAX_JUMP_KM contre
                // un changement de source ou une remise a zero.
                if ($deltaOdo > 0 && $deltaOdo <= 2000) {
                    $km += $deltaOdo;
                }

                $deltaSoc = (float) $point->soc - (float) $precedent->soc;

                if ($deltaSoc < 0) {
                    $socConsomme += abs($deltaSoc);
                }
            }

            $precedent = $point;
        }

        if ($km < 20 || $socConsomme <= 0) {
            return null;
        }

        return round($socConsomme / 100 * $netCapacity / $km * 100, 1);
    }

    /**
     * Positions du jour, espacees d'au moins 150 m pour ne pas superposer des
     * boutons quasi au meme endroit (voiture a l'arret) -- puis
     * echantillonnees a 20 points au plus, repartis sur toute la journee
     * plutot que les 20 premiers : au-dela, des boutons illisibles sur un
     * ecran qu'on ne peut ni zoomer ni faire defiler.
     *
     * @return array<int, array{lat: float, lon: float, heure: string}>
     */
    private function positionsDuJour(?Vehicle $vehicle): array
    {
        if (! $vehicle) {
            return [];
        }

        $points = $vehicle->telemetries()
            ->whereNotNull('lat')->whereNotNull('lon')
            ->whereDate('recorded_at', now(config('app.timezone'))->toDateString())
            ->orderBy('recorded_at')
            ->get(['lat', 'lon', 'recorded_at']);

        $retenus = [];
        $precedent = null;

        foreach ($points as $point) {
            $lat = (float) $point->lat;
            $lon = (float) $point->lon;

            if ($precedent && $this->haversine($precedent['lat'], $precedent['lon'], $lat, $lon) < 0.15) {
                continue;
            }

            $retenus[] = [
                'lat' => $lat,
                'lon' => $lon,
                'heure' => $point->recorded_at->timezone(config('app.timezone'))->format('H:i'),
            ];
            $precedent = ['lat' => $lat, 'lon' => $lon];
        }

        if (count($retenus) > 20) {
            $pas = (int) ceil(count($retenus) / 20);
            $retenus = collect($retenus)->filter(fn ($p, $i) => $i % $pas === 0)->values()->all();
        }

        return $retenus;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function show(Request $request): View|JsonResponse
    {
        // Le graphique de batterie a son propre cycle de rafraichissement
        // (chaque minute), independant du rechargement complet de la page
        // (5 a 20 s selon l'etat du vehicule) : le rouvrir a chaque fois
        // ferait clignoter le reste de l'ecran. Meme chemin public que la
        // page elle-meme -- voir routes/web.php -- pour beneficier sans rien
        // dupliquer de l'exemption passkey du nginx de l'hote, qui ne filtre
        // que sur le chemin et ignore la chaine de requete.
        if ($request->query('flux') === 'batterie') {
            if (! $this->deverrouille($request)) {
                return response()->json(['erreur' => 'verrouille'], 403);
            }

            $vehicle = Vehicle::whereNotNull('mqtt_client_id')
                ->orderByDesc('is_default')->orderBy('id')->first();

            $points = $vehicle
                ? $vehicle->telemetries()
                    ->whereNotNull('soc')
                    ->whereDate('recorded_at', now()->toDateString())
                    ->orderBy('recorded_at')
                    ->get(['soc', 'recorded_at'])
                    ->map(fn ($r) => ['t' => $r->recorded_at->timestamp * 1000, 'soc' => (float) $r->soc])
                    ->values()
                : collect();

            return response()->json(['points' => $points]);
        }

        if (! $this->deverrouille($request)) {
            return view('info_car_gate');
        }

        // Aucun utilisateur en session : le scope par compte est inactif, on
        // designe donc explicitement un vehicule plutot que de prendre « le
        // premier venu », qui pourrait etre celui de quelqu'un d'autre.
        $vehicle = Vehicle::whereNotNull('mqtt_client_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $telemetry = $vehicle?->latestTelemetry;

        $history = $vehicle
            ? $vehicle->telemetries()
                ->where('recorded_at', '>=', now()->subDay())
                ->orderBy('recorded_at')
                ->get()
            : collect();

        $curve = $this->curves->find($vehicle?->charging_curve);
        $netCapacity = $curve['battery_net_kwh'] ?? null;

        $soc = $telemetry?->soc !== null ? (float) $telemetry->soc : null;
        $availableKwh = ($soc !== null && $netCapacity) ? round($soc / 100 * $netCapacity, 1) : null;

        $consumption = $vehicle?->kwh_per_100km ? (float) $vehicle->kwh_per_100km : null;
        $rangeKm = ($availableKwh !== null && $consumption) ? (int) round($availableKwh / $consumption * 100) : null;

        // Pas de tuile "Autonomie (vehicule)" a partir de CLTC_RANGE : ce
        // champ du boitier est mal etiquete, constate le 24/09/2026 --
        // correlation de -0,907 avec le SoC sur 3738 points (plus la batterie
        // est chargee, plus la valeur est basse : 76-90 km a 100%, jusqu'a
        // ~380 km vers 65% de SoC). L'exact inverse d'une autonomie. Meme
        // categorie de piege que cumulativeCharge (voir CLAUDE.md, "Tout
        // champ venant d'un PID peut sortir n'importe quoi") : le nom du
        // champ ne garantit pas son contenu reel.

        // Autonomie a partir de la consommation reellement observee plutot
        // que la fiche du vehicule : uniquement les baisses de SoC sur la
        // fenetre glissante $history (la charge est ignoree, elle fausserait
        // le ratio a cette granularite -- meme raison que DailyVehicleActivity,
        // qui ne calcule ce ratio qu'au mois, jamais au jour).
        $consommationRecente = $this->consommationRecente($history, $netCapacity);
        $rangeDynamique = ($availableKwh !== null && $consommationRecente)
            ? (int) round($availableKwh / $consommationRecente * 100)
            : null;

        $state = $this->state->describe($telemetry, $history);

        $position = ($telemetry?->lat && $telemetry?->lon)
            ? ['lat' => (float) $telemetry->lat, 'lon' => (float) $telemetry->lon]
            : null;

        // Donnee OpenStreetMap (tag maxspeed), pas une lecture de panneau :
        // voir le docblock de SpeedLimitLookup pour ses limites.
        $vitesseLimite = $position
            ? $this->speedLimit->forPosition($position['lat'], $position['lon'])
            : null;

        // La courbe passe par forVehicle() : les paliers releves sur cette
        // voiture priment sur la reference du modele quand ils existent.
        $courbe = $this->curves->forVehicle($vehicle);

        // La commune plutot que le compteur : sur l'ecran de la voiture, savoir
        // ou elle se trouve vaut mieux qu'un kilometrage que le tableau de bord
        // affiche deja. Elle vient du meme cache que « Deplacements » — une
        // voiture a l'arret ne redemande rien a la BAN.
        $ville = $position !== null
            ? $this->geocoder->city($position['lat'], $position['lon'])
            : null;

        $rangeChart = $this->rangeChart($this->rangeSeries($history, $netCapacity, $consumption));

        // Pression des pneus : absente du boitier OBD/MQTT (aucun PID connu
        // pour ca, voir ObdReadings), disponible en revanche cote export
        // officiel Xpeng (XpengDataController) -- pas de notion de vehicule
        // ici, meme convention mono-vehicule que le reste de cette table.
        // Kpa en base, bar a l'affichage, meme conversion que sur la page
        // Donnees Xpeng.
        $pressionPneus = XpengTelemetry::where(fn ($q) => $q
                ->whereNotNull('pression_av_gauche_kpa')
                ->orWhereNotNull('pression_av_droite_kpa')
                ->orWhereNotNull('pression_ar_gauche_kpa')
                ->orWhereNotNull('pression_ar_droite_kpa'))
            ->orderByDesc('horodatage')
            ->first();

        // Signale que le boitier ne publie plus : sans releve recent, tout ce
        // qu'affiche l'onglet Info (batterie, autonomie...) peut etre perime
        // sans que rien ne le distingue autrement. Seuil de deux minutes,
        // largement au-dessus de la cadence normale (5 a 20 s).
        $mqttPerime = ! $telemetry || $telemetry->recorded_at->lt(now()->subMinutes(2));

        return view('info_car', [
            'vehicle' => $vehicle,
            'ville' => $ville,
            'telemetry' => $telemetry,
            'mqttPerime' => $mqttPerime,
            'state' => $state,
            'soc' => $soc,
            'availableKwh' => $availableKwh,
            'netCapacity' => $netCapacity,
            'rangeKm' => $rangeKm,
            'rangeDynamique' => $rangeDynamique,
            'position' => $position,
            'vitesseLimite' => $vitesseLimite,
            'pressionPneus' => $pressionPneus,
            'ecartCellules' => $this->battery->medianGap($history),
            'refreshSeconds' => VehicleState::REFRESH_SECONDS[$state['state'] ?? VehicleState::PARKED]
                ?? VehicleState::REFRESH_SECONDS[VehicleState::PARKED],
            'cibles' => self::CIBLES,
            'recharges' => $this->recharges($courbe, $soc),
            'rangeChart' => $rangeChart,
            'dernieresVilles' => $this->dernieresVilles($vehicle),
            'positionsDuJour' => $this->positionsDuJour($vehicle),
        ]);
    }

    /**
     * Verifie le code saisi au pave numerique. hash_equals() plutot qu'un
     * simple === : le code, bien que court, n'a pas a etre compare en temps
     * variable pour qui observerait les reponses de pres.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $pin = config('services.info_car.pin');

        if ($pin !== null && hash_equals((string) $pin, $data['code'])) {
            return redirect()->route('info-car')
                ->cookie(self::UNLOCK_COOKIE, (string) $pin, self::UNLOCK_MINUTES);
        }

        return redirect()->route('info-car')->with('error', 'Code incorrect.');
    }
}
