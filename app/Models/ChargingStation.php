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
