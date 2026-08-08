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
        'provider_id',
        'power_rating_id',
        'quantity_kwh',
        'charge_duration',
        'parking_duration',
        'unit_cost',
        'total_cost',
        'comment',
    ];

    protected $casts = [
        'session_date' => 'date',
        'quantity_kwh' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function powerRating(): BelongsTo
    {
        return $this->belongsTo(PowerRating::class);
    }
}
