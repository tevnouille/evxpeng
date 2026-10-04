<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recharge mesuree par le boitier OBD, publiee cle en main par XPCarData.
 *
 * Pas de scope par utilisateur : le cloisonnement passe par le vehicule, qui
 * porte deja `user_id`.
 */
class TelemetryChargingSession extends Model
{
    protected $fillable = [
        'vehicle_id',
        'external_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'soc_start',
        'soc_end',
        'energy_kwh',
        'energy_ah',
        'odometer_start',
        'odometer_end',
        'charging_type',
        'max_power_kw',
        'lat',
        'lon',
        'curve',
        'gap_alert_delivered',
        'raw',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'soc_start' => 'decimal:1',
        'soc_end' => 'decimal:1',
        'energy_kwh' => 'decimal:3',
        'energy_ah' => 'decimal:2',
        'max_power_kw' => 'decimal:3',
        'lat' => 'float',
        'lon' => 'float',
        'curve' => 'array',
        'gap_alert_delivered' => 'boolean',
        'raw' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
