<?php

namespace App\Services;

use App\Models\ChargingStation;

/**
 * Ce qui se trouve le long d'un itineraire.
 *
 * Sert au planificateur (choisir ou s'arreter) comme aux trajets favoris
 * (afficher toutes les bornes du parcours) : dans les deux cas la question est
 * la meme, quelles stations sont a moins de X km du trace et a quel kilometre
 * du depart.
 */
class RouteCorridor
{
    /** Pas d'echantillonnage du trace, en km. */
    private const SAMPLE_KM = 2.0;

    /** Cote d'une cellule de l'index spatial, en degres (~11 km en latitude). */
    private const GRID_DEGREES = 0.1;

    /**
     * Reechantillonne le trace a pas constant, avec la distance cumulee.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  paires [lat, lon]
     * @return array<int, array{lat: float, lon: float, km: float}>
     */
    public function sample(array $coordinates): array
    {
        $samples = [];
        $cumulative = 0.0;
        $lastKept = -INF;
        $previous = null;

        foreach ($coordinates as $point) {
            [$lat, $lon] = $point;

            if ($previous !== null) {
                $cumulative += $this->haversine($previous[0], $previous[1], $lat, $lon);
            }

            if ($previous === null || $cumulative - $lastKept >= self::SAMPLE_KM) {
                $samples[] = ['lat' => $lat, 'lon' => $lon, 'km' => $cumulative];
                $lastKept = $cumulative;
            }

            $previous = $point;
        }

        // Le dernier point est l'arrivee : sans lui, une borne proche du terminus
        // serait rattachee a un kilometrage trop court.
        if ($previous !== null) {
            $samples[] = ['lat' => $previous[0], 'lon' => $previous[1], 'km' => $cumulative];
        }

        return $samples;
    }

    /**
     * Bornes situees le long du trace, avec leur progression kilometrique.
     *
     * @param  array<int, array{lat: float, lon: float, km: float}>  $samples
     * @param  array<string, mixed>  $options  min_power, max_detour_km, networks, networks_only
     * @return array<int, array<string, mixed>>
     */
    public function stations(array $samples, array $options): array
    {
        if ($samples === []) {
            return [];
        }

        $detour = (float) ($options['max_detour_km'] ?? 5);
        $networks = array_values(array_filter((array) ($options['networks'] ?? [])));

        $lats = array_column($samples, 'lat');
        $lons = array_column($samples, 'lon');

        // Marge de la boite englobante : 1 degre de latitude vaut ~111 km, la
        // longitude retrecit avec la latitude.
        $padLat = $detour / 111.0;
        $padLon = $detour / max(20.0, 111.0 * cos(deg2rad(array_sum($lats) / count($lats))));

        $query = ChargingStation::query()
            ->whereBetween('lat', [min($lats) - $padLat, max($lats) + $padLat])
            ->whereBetween('lon', [min($lons) - $padLon, max($lons) + $padLon])
            ->where('max_power_kw', '>=', (float) ($options['min_power'] ?? 0))
            ->where('is_public', true);

        // Par defaut les reseaux choisis ne font que peser dans le score du
        // planificateur : mieux vaut une borne hors reseau qu'un plan impossible.
        // Le filtre dur reste disponible pour qui n'a qu'un seul badge.
        if ($networks !== [] && ($options['networks_only'] ?? false)) {
            $query->where(function ($sub) use ($networks) {
                foreach ($networks as $network) {
                    $sub->orWhere('network', $network)->orWhere('operator', $network);
                }
            });
        }

        $grid = [];

        foreach ($samples as $index => $sample) {
            $grid[$this->cell($sample['lat'], $sample['lon'])][] = $index;
        }

        // La cellule la plus etroite fait ~7,5 km (longitude, nord de la France) :
        // il faut donc balayer assez de cellules voisines pour couvrir le detour.
        $span = max(1, (int) ceil($detour / 7.0));
        $found = [];

        foreach ($query->cursor() as $station) {
            $nearest = $this->nearestSample($station, $samples, $grid, $span);

            if ($nearest === null || $nearest['distance'] > $detour) {
                continue;
            }

            $found[] = [
                'km' => round($nearest['km'], 1),
                'detour_km' => round($nearest['distance'], 1),
                'power_kw' => (float) $station->max_power_kw,
                'preferred' => $networks !== []
                    && (in_array($station->network, $networks, true) || in_array($station->operator, $networks, true)),
                'station' => [
                    'id' => $station->id,
                    'name' => $station->name,
                    'network' => $station->network,
                    'operator' => $station->operator,
                    'address' => $station->address,
                    'city' => $station->city,
                    'lat' => (float) $station->lat,
                    'lon' => (float) $station->lon,
                    'power_kw' => (float) $station->max_power_kw,
                    'points_count' => $station->points_count,
                ],
            ];
        }

        usort($found, fn ($a, $b) => $a['km'] <=> $b['km']);

        return $found;
    }

    /**
     * Trace allege pour la carte : au-dela de ~1 500 points, Leaflet rame sans
     * qu'on y gagne en lisibilite.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates
     * @return array<int, array{0: float, 1: float}>
     */
    public function simplify(array $coordinates, int $target = 1500): array
    {
        $step = max(1, (int) ceil(count($coordinates) / $target));
        $simplified = [];

        foreach ($coordinates as $index => $point) {
            if ($index % $step === 0) {
                $simplified[] = [round($point[0], 5), round($point[1], 5)];
            }
        }

        $last = end($coordinates);

        if ($last !== false) {
            $simplified[] = [round($last[0], 5), round($last[1], 5)];
        }

        return $simplified;
    }

    public function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array<int, array{lat: float, lon: float, km: float}>  $samples
     * @param  array<string, array<int, int>>  $grid
     * @return array{km: float, distance: float}|null
     */
    private function nearestSample(ChargingStation $station, array $samples, array $grid, int $span): ?array
    {
        $lat = (float) $station->lat;
        $lon = (float) $station->lon;
        $baseLat = (int) floor($lat / self::GRID_DEGREES);
        $baseLon = (int) floor($lon / self::GRID_DEGREES);

        $best = null;

        for ($dLat = -$span; $dLat <= $span; $dLat++) {
            for ($dLon = -$span; $dLon <= $span; $dLon++) {
                foreach ($grid[($baseLat + $dLat).':'.($baseLon + $dLon)] ?? [] as $index) {
                    $sample = $samples[$index];
                    $distance = $this->haversine($lat, $lon, $sample['lat'], $sample['lon']);

                    if ($best === null || $distance < $best['distance']) {
                        $best = ['km' => $sample['km'], 'distance' => $distance];
                    }
                }
            }
        }

        return $best;
    }

    private function cell(float $lat, float $lon): string
    {
        return ((int) floor($lat / self::GRID_DEGREES)).':'.((int) floor($lon / self::GRID_DEGREES));
    }
}
