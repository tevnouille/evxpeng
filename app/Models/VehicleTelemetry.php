<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTelemetry extends Model
{
    protected $table = 'vehicle_telemetries';

    protected $fillable = [
        'vehicle_id',
        'recorded_at',
        'soc',
        'is_charging',
        'is_connected',
        'lat',
        'lon',
        'telemetry_type',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'soc' => 'decimal:1',
        'is_charging' => 'boolean',
        'is_connected' => 'boolean',
        'lat' => 'float',
        'lon' => 'float',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
