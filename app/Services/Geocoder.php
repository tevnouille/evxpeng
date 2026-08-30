<?php

namespace App\Services;


/**
 * Resolution d'une adresse libre en coordonnees.
 *
 * Deux sources, dans cet ordre : la Base Adresse Nationale (api-adresse.data.gouv.fr),
 * gratuite, sans cle et sans quota genant, mais limitee a la France ; puis
 * Nominatim pour tout ce qui sort du territoire.
 */
class Geocoder
{
    public function __construct(private readonly HttpUserAgent $agent)
    {
    }

    /**
     * @return array{label: string, lat: float, lon: float}|null
     */
    public function locate(string $query): ?array
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        return $this->fromCoordinates($query)
            ?? $this->fromBan($query)
            ?? $this->fromNominatim($query);
    }

    /**
     * Suggestions pour la saisie assistee du formulaire.
     *
     * @return array<int, array{label: string, lat: float, lon: float}>
     */
    public function suggest(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return [];
        }

        $response = $this->agent->get('https://api-adresse.data.gouv.fr/search/', [
            'q' => $query,
            'limit' => $limit,
        ]);

        if ($response === null) {
            return [];
        }

        return collect($response['features'] ?? [])
            ->map(fn ($feature) => $this->fromBanFeature($feature))
            ->filter()
            ->values()
            ->all();
    }

    /** Saisie directe "48.5836, 2.4436", pratique depuis la carte. */
    private function fromCoordinates(string $query): ?array
    {
        if (! preg_match('/^\s*(-?\d{1,2}(?:[.,]\d+)?)\s*[,;]\s*(-?\d{1,3}(?:[.,]\d+)?)\s*$/', $query, $matches)) {
            return null;
        }

        $lat = (float) str_replace(',', '.', $matches[1]);
        $lon = (float) str_replace(',', '.', $matches[2]);

        if (abs($lat) > 90 || abs($lon) > 180) {
            return null;
        }

        return [
            'label' => sprintf('%.5f, %.5f', $lat, $lon),
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    private function fromBan(string $query): ?array
    {
        $response = $this->agent->get('https://api-adresse.data.gouv.fr/search/', [
            'q' => $query,
            'limit' => 1,
        ]);

        $feature = $response['features'][0] ?? null;

        return $feature ? $this->fromBanFeature($feature) : null;
    }

    private function fromBanFeature(array $feature): ?array
    {
        $coordinates = $feature['geometry']['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $properties = $feature['properties'] ?? [];
        $context = $properties['context'] ?? '';

        return [
            'label' => trim(($properties['label'] ?? '').($context !== '' ? ' ('.$context.')' : '')),
            'lat' => (float) $coordinates[1],
            'lon' => (float) $coordinates[0],
        ];
    }

    private function fromNominatim(string $query): ?array
    {
        $response = $this->agent->get('https://nominatim.openstreetmap.org/search', [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
        ]);

        $result = $response[0] ?? null;

        if (! is_array($result)) {
            return null;
        }

        return [
            'label' => (string) ($result['display_name'] ?? $query),
            'lat' => (float) $result['lat'],
            'lon' => (float) $result['lon'],
        ];
    }
}
