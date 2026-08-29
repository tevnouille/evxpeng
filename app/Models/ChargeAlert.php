<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeAlert extends Model
{
    protected $fillable = ['vehicle_id', 'session_started_at', 'threshold', 'soc', 'delivered'];

    protected $casts = [
        'session_started_at' => 'datetime',
        'soc' => 'decimal:1',
        'delivered' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
