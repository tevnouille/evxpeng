<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un releve Xpeng, agrege a la minute.
 *
 * Les fichiers d'export Xpeng sont a la seconde (App\Services\XpengExportParser)
 * mais rien n'est jamais importe a cette granularite : ~1 million de lignes par
 * mois et par jeu de donnees, ce qui romprait la regle « jamais purger » deja en
 * place pour vehicle_telemetries (CLAUDE.md), pensee pour un volume negligeable.
 * Les fichiers bruts, eux, sont conserves tels quels (storage/app/xpeng) : la
 * finesse seconde par seconde n'est jamais perdue, seulement pas mise en base.
 */
class XpengTelemetry extends Model
{
    protected $table = 'xpeng_telemetries';

    protected $fillable = [
        'vin',
        'horodatage',
        'nb_releves',
        'vitesse_moy_kmh',
        'vitesse_max_kmh',
        'odometre_km',
        'soc_moy',
        'soc_min',
        'soc_max',
        'autonomie_km',
        'puissance_charge_moy_kw',
        'temp_batterie_max_c',
        'temp_batterie_min_c',
        'cellule_temp_max_num',
        'cellule_temp_min_num',
        'pression_av_gauche_kpa',
        'pression_av_droite_kpa',
        'pression_ar_gauche_kpa',
        'pression_ar_droite_kpa',
    ];

    protected $casts = [
        'horodatage' => 'datetime',
    ];
}
