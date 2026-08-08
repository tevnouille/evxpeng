<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PowerRating extends Model
{
    use HasFactory;

    protected $fillable = ['kw'];

    protected $casts = [
        'kw' => 'decimal:2',
    ];

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }
}
