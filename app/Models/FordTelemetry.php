<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un releve FordConnect Query, une ligne par execution de `ford:sync`.
 */
class FordTelemetry extends Model
{
    protected $table = 'ford_telemetries';

    protected $fillable = [
        'vin',
        'recorded_at',
        'soc',
        'odometre_km',
        'battery_voltage',
        'ambient_temp_c',
        'outside_temp_c',
        'ignition_status',
        'metrics',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'metrics' => 'array',
    ];
}
