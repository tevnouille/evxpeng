<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargingStation extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'network',
        'operator',
        'address',
        'city',
        'lat',
        'lon',
        'max_power_kw',
        'points_count',
        'has_ccs',
        'has_type2',
        'has_chademo',
        'is_free',
        'is_public',
    ];

    /**
     * Reseaux proposes aux filtres du planificateur et des trajets favoris.
     *
     * C'est l'operateur (le CPO) qui fait le reseau, pas l'enseigne : IRVE
     * renseigne `nom_enseigne` site par site, si bien qu'Electra y apparait sous
     * 400 libelles differents ("Electra Villejuif", "Electra Vitrolles"...) alors
     * que `nom_operateur` vaut partout "ELECTRA".
     *
     * Liste alphabetique et non par volume : elle se parcourt depuis un champ de
     * recherche, ou l'ordre par frequence n'aide plus.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function networkOptions(int $minimum = 5)
    {
        return static::query()
            ->whereNotNull('operator')
            ->where('operator', '!=', '')
            ->selectRaw('operator as network, COUNT(*) as stations')
            ->groupBy('operator')
            ->havingRaw('COUNT(*) >= ?', [$minimum])
            ->orderBy('operator')
            ->get();
    }

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
        'max_power_kw' => 'float',
        'points_count' => 'integer',
        'has_ccs' => 'boolean',
        'has_type2' => 'boolean',
        'has_chademo' => 'boolean',
        'is_free' => 'boolean',
        'is_public' => 'boolean',
    ];
}
