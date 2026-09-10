<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appreciation personnelle sur une borne (fiable, HS, acces complique).
 *
 * Une par (utilisateur, borne) : la reecrire remplace l'observation
 * precedente. Pas d'historique de passages — voir la migration.
 */
class ChargingStationNote extends Model
{
    use BelongsToUser;

    protected $fillable = ['charging_station_id', 'note'];

    public function station(): BelongsTo
    {
        return $this->belongsTo(ChargingStation::class, 'charging_station_id');
    }
}
