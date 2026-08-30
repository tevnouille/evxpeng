<?php

namespace App\Http\Controllers;

use App\Models\ChargingStation;
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
            ->get(['name', 'city', 'address', 'operator', 'network', 'max_power_kw']);

        return response()->json([
            'results' => $stations->map(fn ($station) => [
                'name' => $station->name,
                'city' => $station->city,
                'address' => $station->address,
                'operator' => $station->operator ?: $station->network,
                'power_kw' => (float) $station->max_power_kw,
            ])->values(),
        ]);
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
