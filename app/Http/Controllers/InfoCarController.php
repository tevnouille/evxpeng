<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\BatteryHealth;
use App\Services\ChargeCurveSimulator;
use App\Services\ChargingCurveRepository;
use App\Services\ReverseGeocoder;
use App\Services\VehicleState;
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
 */
class InfoCarController extends Controller
{
    public function __construct(
        private readonly VehicleState $state,
        private readonly ChargingCurveRepository $curves,
        private readonly ChargeCurveSimulator $simulator,
        private readonly ReverseGeocoder $geocoder,
        private readonly BatteryHealth $battery,
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
        $marge = 12;

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

        $points = [];

        foreach ($series as $i => $point) {
            $x = $marge + ($instants[$i] - $tempsMin) / $tempsEtendue * ($width - 2 * $marge);
            $y = $height - $marge - ($point['km'] - $kmMin) / $kmEtendue * ($height - 2 * $marge);
            $points[] = round($x, 1).','.round($y, 1);
        }

        return [
            'points' => implode(' ', $points),
            'width' => $width,
            'height' => $height,
            'km_min' => $kmMin,
            'km_max' => $kmMax,
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

    public function show(): View
    {
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

        $state = $this->state->describe($telemetry, $history);

        $position = ($telemetry?->lat && $telemetry?->lon)
            ? ['lat' => (float) $telemetry->lat, 'lon' => (float) $telemetry->lon]
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

        return view('info_car', [
            'vehicle' => $vehicle,
            'ville' => $ville,
            'telemetry' => $telemetry,
            'state' => $state,
            'soc' => $soc,
            'availableKwh' => $availableKwh,
            'netCapacity' => $netCapacity,
            'rangeKm' => $rangeKm,
            'position' => $position,
            'ecartCellules' => $this->battery->medianGap($history),
            'refreshSeconds' => VehicleState::REFRESH_SECONDS[$state['state'] ?? VehicleState::PARKED]
                ?? VehicleState::REFRESH_SECONDS[VehicleState::PARKED],
            'cibles' => self::CIBLES,
            'recharges' => $this->recharges($courbe, $soc),
            'rangeChart' => $rangeChart,
        ]);
    }
}
