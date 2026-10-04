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
    /**
     * Ecart de score tolere pour preferer une commune au premier resultat.
     *
     * "Chambery" seul renvoie d'abord la rue Chambery de Beaupreau-en-Mauges
     * (0,958) avant la ville de Savoie (0,954) : quatre millemes separent le
     * lieu-dit de la prefecture. Une marge de 0,10 rattrape ces coudees sans
     * detourner une vraie recherche de rue, ou la commune tombe bien plus bas.
     */
    private const MUNICIPALITY_MARGIN = 0.10;

    public function __construct(private readonly HttpUserAgent $agent)
    {
    }

    /**
     * Point choisi explicitement dans la liste de suggestions, sinon geocodage
     * du texte saisi.
     *
     * Les coordonnees retenues a la selection font foi : re-geocoder le libelle
     * affiche peut retomber ailleurs, la BAN ne rendant pas toujours le meme
     * resultat pour le texte qu'elle vient elle-meme de proposer.
     *
     * @return array{label: string, lat: float, lon: float}|null
     */
    public function resolve(?string $query, mixed $lat = null, mixed $lon = null): ?array
    {
        if (is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180) {
            $label = trim((string) $query);

            return [
                'label' => $label !== '' ? $label : sprintf('%.5f, %.5f', (float) $lat, (float) $lon),
                'lat' => (float) $lat,
                'lon' => (float) $lon,
            ];
        }

        return $this->locate((string) $query);
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
     * `label` et `context` sont separes a dessein : le contexte sert a
     * departager deux homonymes a l'ecran, mais l'accoler au libelle rendrait le
     * texte inexploitable par la BAN — "Lyon (69, Rhone, Auvergne-Rhone-Alpes)"
     * renvoie un chemin d'une autre commune.
     *
     * @return array<int, array{label: string, context: string, lat: float, lon: float}>
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
            'context' => '',
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    private function fromBan(string $query): ?array
    {
        $response = $this->agent->get('https://api-adresse.data.gouv.fr/search/', [
            'q' => $query,
            'limit' => 5,
        ]);

        $features = $response['features'] ?? [];

        if (! is_array($features) || $features === []) {
            return null;
        }

        return $this->fromBanFeature($this->preferMunicipality($features, $query));
    }

    /**
     * Une saisie sans chiffre est presque toujours un nom de ville : on remonte
     * la commune si elle est au coude a coude avec le premier resultat.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private function preferMunicipality(array $features, string $query): array
    {
        $best = $features[0];

        if (preg_match('/\d/', $query)) {
            return $best;
        }

        $topScore = (float) ($best['properties']['score'] ?? 0);

        foreach ($features as $feature) {
            if (($feature['properties']['type'] ?? '') !== 'municipality') {
                continue;
            }

            if ((float) ($feature['properties']['score'] ?? 0) >= $topScore - self::MUNICIPALITY_MARGIN) {
                return $feature;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array{label: string, context: string, lat: float, lon: float}|null
     */
    private function fromBanFeature(array $feature): ?array
    {
        $coordinates = $feature['geometry']['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $properties = $feature['properties'] ?? [];

        return [
            'label' => (string) ($properties['label'] ?? ''),
            'context' => (string) ($properties['context'] ?? ''),
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
            'context' => '',
            'lat' => (float) $result['lat'],
            'lon' => (float) $result['lon'],
        ];
    }
}
