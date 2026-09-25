<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une ligne par declenchement du garage/portail (App\Services\MerossClient).
 * Pas de BelongsToUser : voir le docblock de la migration.
 */
class MerossAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'appareil',
        'action',
        'reussi',
        'erreur',
        'created_at',
    ];

    protected $casts = [
        'reussi' => 'boolean',
        'created_at' => 'datetime',
    ];
}
