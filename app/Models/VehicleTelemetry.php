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
        'odometer',
        'soh',
        'power_kw',
        'batt_temp',
        'ext_temp',
        'speed',
        'is_dcfc',
        'is_parked',
        'raw',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'soc' => 'decimal:1',
        'is_charging' => 'boolean',
        'is_connected' => 'boolean',
        'lat' => 'float',
        'lon' => 'float',
        'raw' => 'array',
        'soh' => 'decimal:1',
        'power_kw' => 'decimal:3',
        'batt_temp' => 'decimal:1',
        'ext_temp' => 'decimal:1',
        'speed' => 'decimal:1',
        'is_dcfc' => 'boolean',
        'is_parked' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
