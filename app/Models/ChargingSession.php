<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_date',
        'vehicle_id',
        'telemetry_started_at',
        'location_id',
        'provider_id',
        'power_rating_id',
        'quantity_kwh',
        'charge_duration',
        'unit_cost',
        'total_cost',
        'comment',
    ];

    protected $casts = [
        'session_date' => 'date',
        'telemetry_started_at' => 'datetime',
        'quantity_kwh' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'charge_duration' => 'datetime:H:i',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function powerRating(): BelongsTo
    {
        return $this->belongsTo(PowerRating::class);
    }
}
