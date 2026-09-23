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
        'xev_soc',
        'xev_range_km',
        'xev_energy_remaining_kwh',
        'xev_time_to_full_charge_min',
        'xev_charger_energy_output_kwh',
        'xev_charger_current_output_a',
        'xev_charger_voltage_output_v',
        'plug_status',
        'charge_display_status',
        'charge_station_power_type',
        'speed_kmh',
        'latitude',
        'longitude',
        'pression_av_gauche_kpa',
        'pression_av_droite_kpa',
        'pression_ar_gauche_kpa',
        'pression_ar_droite_kpa',
        'metrics',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'metrics' => 'array',
    ];
}
