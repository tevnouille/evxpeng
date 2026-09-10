<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Temperature moyenne du jour, mise en cache (App\Services\WeatherService).
 *
 * Table commune et non par utilisateur : la temperature est une donnee
 * publique, meme regime que fuel_prices et charging_stations.
 */
class DailyWeather extends Model
{
    protected $table = 'daily_weather';

    protected $fillable = ['date', 'lat', 'lon', 'temp_min_c', 'temp_max_c', 'temp_mean_c'];

    protected $casts = [
        'date' => 'date',
        'lat' => 'float',
        'lon' => 'float',
        'temp_min_c' => 'decimal:1',
        'temp_max_c' => 'decimal:1',
        'temp_mean_c' => 'decimal:1',
    ];
}
