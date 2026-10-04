<?php

namespace App\Http\Controllers;

use App\Models\ChargingStation;
use App\Services\RouteCorridor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche dans la base nationale des bornes, pour assister la saisie d'une
 * recharge.
 *
 * Les listes de reference de l'utilisateur (localisations, fournisseurs) sont
 * les siennes et restent maitresses ; ces suggestions servent a remplir le
 * champ "Autre..." sans avoir a retaper "TotalEnergies Charging Services".
 */
class ChargerLookupController extends Controller
{
    private const LIMIT = 12;

    /** Au-dela, la requete coute plus qu'elle n'affine. */
    private const MAX_TERMS = 4;

    /** Colonnes fouillees par la recherche. */
    private const SEARCHABLE = ['city', 'name', 'operator', 'address'];

    /**
     * Rayons successifs, en kilometres, pour la recherche par proximite.
     *
     * On commence serre — en ville il y a des dizaines de bornes a moins de
     * deux kilometres — et on n'elargit que si rien ne repond, pour ne pas
     * noyer une borne toute proche sous des resultats a trente kilometres.
     */
    private const RADII_KM = [2, 10, 40];

    /** Un degre de latitude vaut environ cette distance, partout sur le globe. */
    private const KM_PER_DEGREE = 111.0;

    public function stations(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        // Chaque mot doit se retrouver quelque part, pas forcement dans la meme
        // colonne : "tesla villabe" croise l'operateur et la commune, et ne
        // donnait rien tant que les colonnes etaient testees isolement.
        $terms = array_slice(preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_TERMS);

        $stations = ChargingStation::query()
            ->where('is_public', true)
            ->where(function ($outer) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%'.addcslashes($term, '%_\\').'%';

                    $outer->where(function ($sub) use ($like) {
                        foreach (self::SEARCHABLE as $column) {
                            $sub->orWhere($column, 'like', $like);
                        }
                    });
                }
            })
            // Une commune qui commence par la saisie passe devant : on cherche
            // le plus souvent la ville ou l'on s'est arrete.
            ->orderByRaw('CASE WHEN city LIKE ? THEN 0 ELSE 1 END', [addcslashes($terms[0] ?? $query, '%_\\').'%'])
            ->orderByDesc('max_power_kw')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'city', 'address', 'operator', 'network', 'max_power_kw']);

        return response()->json([
            'results' => $stations->map(fn ($station) => [
                // Inutilise par l'autocomplete de saisie de recharge (elle ne
                // remplit que des champs texte) ; sert a App\Http\Controllers\ChargingStationNoteController
                // pour retrouver la borne exacte plutot que son seul libelle.
                'id' => $station->id,
                'name' => $station->name,
                'city' => $station->city,
                'address' => $station->address,
                'operator' => $station->operator ?: $station->network,
                'power_kw' => (float) $station->max_power_kw,
            ])->values(),
        ]);
    }

    /**
     * Bornes les plus proches d'une position.
     *
     * La position vient du navigateur, jamais d'un enregistrement : elle sert
     * le temps de la requete et n'est pas conservee.
     */
    public function nearby(Request $request, RouteCorridor $corridor): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $data['lat'];
        $lon = (float) $data['lon'];

        foreach (self::RADII_KM as $radius) {
            $stations = $this->within($lat, $lon, $radius, $corridor);

            if ($stations !== []) {
                return response()->json(['radius_km' => $radius, 'results' => $stations]);
            }
        }

        // end() attend une reference : une constante de classe ne peut pas lui
        // etre passee.
        return response()->json(['radius_km' => self::RADII_KM[count(self::RADII_KM) - 1], 'results' => []]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function within(float $lat, float $lon, int $radius, RouteCorridor $corridor): array
    {
        // Cadre grossier d'abord, pour que l'index (lat, lon) travaille ; la
        // distance exacte n'est calculee que sur les quelques lignes retenues.
        $deltaLat = $radius / self::KM_PER_DEGREE;
        $cosine = max(0.01, cos(deg2rad($lat)));
        $deltaLon = $radius / (self::KM_PER_DEGREE * $cosine);

        $stations = ChargingStation::query()
            ->where('is_public', true)
            ->whereBetween('lat', [$lat - $deltaLat, $lat + $deltaLat])
            ->whereBetween('lon', [$lon - $deltaLon, $lon + $deltaLon])
            ->get(['name', 'city', 'address', 'operator', 'network', 'max_power_kw', 'lat', 'lon']);

        return $stations
            ->map(function ($station) use ($lat, $lon, $corridor) {
                $station->distance_km = $corridor->haversine($lat, $lon, (float) $station->lat, (float) $station->lon);

                return $station;
            })
            // Le cadre est un carre, le rayon un cercle : sans ce filtre, les
            // coins renverraient des bornes plus loin que la distance annoncee.
            ->filter(fn ($station) => $station->distance_km <= $radius)
            ->sortBy('distance_km')
            // La base nationale declare parfois plusieurs fois la meme station
            // sous des identifiants differents : sans ce regroupement, trois
            // lignes identiques mangeaient la liste des bornes proches.
            ->unique(fn ($station) => implode('|', [
                mb_strtolower(trim($station->name)),
                mb_strtolower(trim((string) $station->operator)),
                number_format((float) $station->lat, 4, '.', ''),
                number_format((float) $station->lon, 4, '.', ''),
            ]))
            ->take(self::LIMIT)
            ->map(fn ($station) => [
                'name' => $station->name,
                'city' => $station->city,
                'address' => $station->address,
                'operator' => $station->operator ?: $station->network,
                'power_kw' => (float) $station->max_power_kw,
                'lat' => (float) $station->lat,
                'lon' => (float) $station->lon,
                'distance_km' => round($station->distance_km, 1),
            ])
            ->values()
            ->all();
    }

    /** Enseignes et operateurs, pour le champ "Autre..." des fournisseurs. */
    public function operators(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.addcslashes($query, '%_\\').'%';

        $operators = ChargingStation::query()
            ->whereNotNull('operator')
            ->where('operator', '!=', '')
            ->where('operator', 'like', $like)
            ->selectRaw('operator, COUNT(*) as stations')
            ->groupBy('operator')
            ->orderByDesc('stations')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'results' => $operators->map(fn ($row) => [
                'name' => $row->operator,
                'stations' => (int) $row->stations,
            ])->values(),
        ]);
    }
}
