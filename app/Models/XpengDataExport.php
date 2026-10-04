<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une tentative de recuperation des donnees Xpeng.
 *
 * L'API ne renvoie pas de donnees directement : elle produit un fichier a
 * telecharger, disponible ~30 secondes seulement (App\Services\XpengClient).
 * Une ligne par tentative, reussie ou non, pour ne jamais depasser en silence
 * le quota de 5 soumissions/24h impose par Xpeng.
 */
class XpengDataExport extends Model
{
    protected $table = 'xpeng_data_exports';

    protected $fillable = [
        'requested_at',
        'statut',
        'reponse_brute',
        'chemin_fichier',
        'erreur',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];
}
