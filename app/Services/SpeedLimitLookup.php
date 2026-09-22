<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Limite de vitesse de la route la plus proche d'une position, via Overpass
 * (donnees OpenStreetMap) -- pas de la reconnaissance de panneau, juste ce
 * que le tronçon porte comme tag `maxspeed` dans OSM. Peut donc manquer, etre
 * perime, ou generique (FR:urban...) sur les petites routes.
 *
 * Mis en cache par position : Overpass est un service public, gratuit et
 * sans cle, mais pas fait pour etre interroge a chaque rafraichissement
 * d'InfoCar (5 a 20 s en roulant) -- seul un deplacement de plus de 150 m
 * depuis la derniere position interrogee redeclenche un appel.
 */
class SpeedLimitLookup
{
    private const RAYON_METRES = 30;

    private const DISTANCE_MIN_NOUVELLE_REQUETE_KM = 0.15;

    private const CACHE_TTL_MINUTES = 15;

    private const CACHE_KEY = 'speed_limit_lookup';

    /** Zones nommees FR courantes, faute de valeur numerique dans le tag. */
    private const ZONES = [
        'FR:urban' => 50,
        'FR:zone30' => 30,
        'FR:rural' => 80,
        'FR:motorway' => 130,
        'FR:trunk' => 110,
        'FR:living_street' => 20,
        'walk' => 20,
    ];

    public function __construct(private readonly HttpUserAgent $http)
    {
    }

    public function forPosition(float $lat, float $lon): ?int
    {
        $cache = Cache::get(self::CACHE_KEY);

        if ($cache && $this->haversine($cache['lat'], $cache['lon'], $lat, $lon) < self::DISTANCE_MIN_NOUVELLE_REQUETE_KM) {
            return $cache['limite'];
        }

        $limite = $this->query($lat, $lon);

        Cache::put(self::CACHE_KEY, ['lat' => $lat, 'lon' => $lon, 'limite' => $limite], now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $limite;
    }

    private function query(float $lat, float $lon): ?int
    {
        $ql = sprintf(
            '[out:json][timeout:10];way(around:%d,%F,%F)[highway][maxspeed];out tags center;',
            self::RAYON_METRES,
            $lat,
            $lon
        );

        $reponse = $this->http->get('https://overpass-api.de/api/interpreter', ['data' => $ql], 12);

        if (! $reponse || empty($reponse['elements'])) {
            return null;
        }

        $plusProche = null;
        $distanceMin = null;

        foreach ($reponse['elements'] as $element) {
            $centre = $element['center'] ?? null;
            $maxspeed = $element['tags']['maxspeed'] ?? null;

            if (! $centre || ! $maxspeed) {
                continue;
            }

            $distance = $this->haversine($lat, $lon, (float) $centre['lat'], (float) $centre['lon']);

            if ($distanceMin === null || $distance < $distanceMin) {
                $distanceMin = $distance;
                $plusProche = $maxspeed;
            }
        }

        return $plusProche !== null ? $this->normaliser($plusProche) : null;
    }

    private function normaliser(string $valeur): ?int
    {
        if (ctype_digit($valeur)) {
            return (int) $valeur;
        }

        return self::ZONES[$valeur] ?? null;
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
}
