<?php

namespace App\Services;

/**
 * Itineraire routier, via OSRM.
 *
 * Le serveur de demonstration public suffit au volume d'une application
 * personnelle ; l'URL reste configurable pour pouvoir basculer sur une instance
 * privee si la demo se met a refuser les appels.
 */
class RouteService
{
    public function __construct(private readonly HttpUserAgent $agent)
    {
    }

    /**
     * @param  array{lat: float, lon: float}  $from
     * @param  array{lat: float, lon: float}  $to
     * @return array{distance_km: float, duration_minutes: float, coordinates: array<int, array{0: float, 1: float}>}|null
     */
    public function route(array $from, array $to): ?array
    {
        $path = sprintf(
            '%s/route/v1/driving/%F,%F;%F,%F',
            rtrim(config('services.osrm.base_url'), '/'),
            $from['lon'], $from['lat'], $to['lon'], $to['lat']
        );

        // "full" et non "simplified" : le trace sert a mesurer la progression
        // kilometrique le long du parcours, une geometrie degrossie fausserait
        // les distances aux bornes.
        $payload = $this->agent->get($path, [
            'overview' => 'full',
            'geometries' => 'geojson',
            'alternatives' => 'false',
            'steps' => 'false',
        ], 40);

        $route = $payload['routes'][0] ?? null;

        if (($payload['code'] ?? null) !== 'Ok' || ! is_array($route)) {
            return null;
        }

        return [
            'distance_km' => round(((float) $route['distance']) / 1000, 1),
            'duration_minutes' => round(((float) $route['duration']) / 60, 1),
            // OSRM renvoie du [lon, lat] ; on remet dans l'ordre [lat, lon] des
            // ici, celui de Leaflet et de tout le reste de l'application.
            'coordinates' => array_map(
                fn ($point) => [(float) $point[1], (float) $point[0]],
                $route['geometry']['coordinates'] ?? []
            ),
        ];
    }
}
