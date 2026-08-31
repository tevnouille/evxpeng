<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une detection de recharge que l'utilisateur a ecartee de ses propositions.
 *
 * Pas de scope par utilisateur ici : le cloisonnement passe par le vehicule,
 * qui porte deja `user_id`. Les ecritures resolvent donc toujours le vehicule
 * avant d'enregistrer, ce qui les soumet a son scope.
 */
class IgnoredTelemetryCharge extends Model
{
    protected $fillable = [
        'vehicle_id',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
