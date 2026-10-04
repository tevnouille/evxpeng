<?php

namespace App\Services;

use App\Models\Vehicle;

/**
 * Planificateur d'itineraire avec arrets de recharge.
 *
 * L'API de planification d'Iternio (celle qui fait tourner ABRP) est payante et
 * refuse la cle "Telemetry-Only" de l'application : elle repond 403 "Feature
 * plan is not available". Le plan est donc calcule ici, a partir de trois
 * briques deja disponibles gratuitement :
 *
 *  - l'itineraire routier, via OSRM ;
 *  - les bornes, via la base nationale IRVE importee en local ;
 *  - la courbe de recharge du vehicule, deja utilisee par /courbe-de-recharge.
 *
 * Le modele est volontairement simple : consommation constante, pas de relief,
 * pas de meteo, pas de vitesse reelle. Il donne un ordre de grandeur et une
 * liste d'arrets credibles, pas une prevision au kilometre pres.
 */
class RoutePlanner
{
    /** Distance minimale entre deux arrets : s'arreter au bout de 10 km n'a pas de sens. */
    private const MIN_LEG_KM = 25.0;

    /** Garde-fou : au-dela, c'est que la simulation ne converge pas. */
    private const MAX_STOPS = 12;

    /** Marge ajoutee au niveau d'arrivee vise, en points de SoC. */
    private const ARRIVAL_BUFFER = 3.0;

    /** Recharge minimale a un arret : descendre plus bas ne vaut pas le detour. */
    private const MIN_CHARGE_POINTS = 5.0;

    public function __construct(
        private readonly Geocoder $geocoder,
        private readonly RouteService $router,
        private readonly RouteCorridor $corridor,
        private readonly ChargeCurveSimulator $simulator,
        private readonly ChargingCurveRepository $curves,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function plan(Vehicle $vehicle, array $options): array
    {
        $curve = $this->curves->find($vehicle->charging_curve);

        if ($curve === null) {
            return $this->failure('Aucune courbe de recharge n\'est associée à ce véhicule.');
        }

        $from = $this->geocoder->resolve((string) $options['from'], $options['from_lat'] ?? null, $options['from_lon'] ?? null);
        $to = $this->geocoder->resolve((string) $options['to'], $options['to_lat'] ?? null, $options['to_lon'] ?? null);

        if ($from === null) {
            return $this->failure('Adresse de départ introuvable.');
        }

        if ($to === null) {
            return $this->failure('Adresse d\'arrivée introuvable.');
        }

        $route = $this->router->route($from, $to);

        if ($route === null || $route['coordinates'] === []) {
            return $this->failure('Aucun itinéraire routier trouvé entre ces deux points.');
        }

        $samples = $this->corridor->sample($route['coordinates']);
        $total = $route['distance_km'];

        $consumption = (float) $options['consumption'];
        $usable = (float) ($curve['battery_net_kwh'] ?? $curve['battery_kwh']);
        // Kilometres gagnes par point de charge : c'est l'unite de travail de
        // toute la simulation.
        $kmPerPercent = $consumption > 0 ? $usable / $consumption : 0.0;

        if ($kmPerPercent <= 0) {
            return $this->failure('Consommation invalide.');
        }

        $candidates = $this->corridor->stations($samples, $options);
        $simulation = $this->simulate($curve, $total, $kmPerPercent, $candidates, $options);

        if (isset($simulation['error'])) {
            // On rend quand meme l'itineraire : voir le trace et le nombre de
            // bornes ecartees aide a comprendre pourquoi le plan a echoue.
            return $simulation + [
                'ok' => false,
                'from' => $from,
                'to' => $to,
                'distance_km' => $total,
                'geometry' => $this->corridor->simplify($route['coordinates']),
                'candidates_count' => count($candidates),
            ];
        }

        $chargingMinutes = (int) round(collect($simulation['stops'])->sum('minutes'));
        $drivingMinutes = (int) round($route['duration_minutes']);

        return [
            'ok' => true,
            'from' => $from,
            'to' => $to,
            'distance_km' => $total,
            'driving_minutes' => $drivingMinutes,
            'charging_minutes' => $chargingMinutes,
            'total_minutes' => $drivingMinutes + $chargingMinutes,
            'stops' => $simulation['stops'],
            'legs' => $simulation['legs'],
            'arrival_soc' => $simulation['arrival_soc'],
            'energy_kwh' => round($total * $consumption / 100, 1),
            'geometry' => $this->corridor->simplify($route['coordinates']),
            'candidates_count' => count($candidates),
            'consumption' => $consumption,
            'range_km' => (int) round(100 * $kmPerPercent),
        ];
    }

    /**
     * Deroule le trajet et place les arrets.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    private function simulate(array $curve, float $total, float $kmPerPercent, array $candidates, array $options): array
    {
        $soc = (float) $options['start_soc'];
        $reserve = (float) $options['reserve_soc'];
        $arrival = (float) $options['arrival_soc'];
        $maxSoc = (float) $options['max_soc'];
        $curveMax = (float) ($curve['max_power_kw'] ?? 0);

        $position = 0.0;
        $stops = [];
        $legs = [];
        $legStartSoc = $soc;

        while (count($stops) < self::MAX_STOPS) {
            $remaining = $total - $position;
            $socAtDestination = $soc - $remaining / $kmPerPercent;

            if ($socAtDestination >= $arrival) {
                $legs[] = $this->leg($position, $total, $soc, $socAtDestination);

                return [
                    'stops' => $stops,
                    'legs' => $legs,
                    'arrival_soc' => round($socAtDestination, 1),
                ];
            }

            // Portee restante avant de tomber sous la reserve.
            $reach = $position + max(0.0, ($soc - $reserve) * $kmPerPercent);

            $stop = $this->pickStop($candidates, $position, $reach);

            if ($stop === null) {
                return [
                    'error' => sprintf(
                        'Aucune borne retenue entre le km %d et le km %d, là où le véhicule doit se recharger. '
                        .'Élargissez le détour, baissez la puissance minimale ou levez le filtre réseau.',
                        (int) round($position),
                        (int) round($reach)
                    ),
                ];
            }

            $socIn = $soc - ($stop['km'] - $position) / $kmPerPercent;
            $legs[] = $this->leg($position, $stop['km'], $legStartSoc, $socIn);

            // Assez pour finir le trajet si la borne le permet, sinon le plafond
            // de charge choisi : au-dela de 80 % la courbe s'effondre et on perd
            // plus de temps a la borne qu'on n'en gagne sur la route.
            $needed = $arrival + self::ARRIVAL_BUFFER + ($total - $stop['km']) / $kmPerPercent;
            $target = min($maxSoc, max($needed, $socIn + self::MIN_CHARGE_POINTS));
            $target = min(100.0, $target);

            $cap = $curveMax > 0 ? min($stop['power_kw'], $curveMax) : $stop['power_kw'];
            $minutes = $this->simulator->duration($curve, $socIn, $target, $cap);

            if ($minutes === null) {
                return ['error' => 'Le temps de charge n\'a pas pu être calculé sur cette courbe.'];
            }

            $stops[] = [
                'station' => $stop['station'],
                'km' => round($stop['km'], 1),
                'detour_km' => round($stop['detour_km'], 1),
                'power_kw' => $stop['power_kw'],
                'effective_kw' => round($cap, 1),
                'soc_in' => round($socIn, 1),
                'soc_out' => round($target, 1),
                'minutes' => (int) round($minutes / 60),
                'energy_kwh' => $this->simulator->energy($curve, $socIn, $target),
                'average_kw' => $this->simulator->averagePower($curve, $socIn, $target, $cap),
            ];

            $soc = $target;
            $legStartSoc = $target;
            $position = $stop['km'];
        }

        return ['error' => 'Trop d\'arrêts nécessaires : vérifiez la consommation et le niveau de départ.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function leg(float $from, float $to, float $socFrom, float $socTo): array
    {
        return [
            'from_km' => round($from, 1),
            'to_km' => round($to, 1),
            'km' => round($to - $from, 1),
            'soc_start' => round($socFrom, 1),
            'soc_end' => round($socTo, 1),
        ];
    }

    /**
     * Choisit la borne d'un arret dans la fenetre atteignable.
     *
     * On veut aller le plus loin possible, mais pas au prix d'une borne lente ou
     * d'un long detour : le score arbitre entre les trois.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    private function pickStop(array $candidates, float $position, float $reach): ?array
    {
        $window = $reach - $position;

        if ($window <= 0) {
            return null;
        }

        foreach ([self::MIN_LEG_KM, 0.0] as $minimum) {
            $best = null;
            $bestScore = -INF;

            foreach ($candidates as $candidate) {
                if ($candidate['km'] <= $position + $minimum || $candidate['km'] > $reach) {
                    continue;
                }

                $progress = ($candidate['km'] - $position) / $window;
                $score = $progress
                    + 0.35 * min(1.0, $candidate['power_kw'] / 150)
                    + ($candidate['preferred'] ? 0.25 : 0.0)
                    - 0.03 * $candidate['detour_km'];

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                }
            }

            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
