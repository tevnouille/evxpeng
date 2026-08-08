<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_default', 'kwh_per_100km', 'essence_l_per_100km', 'diesel_l_per_100km'];

    protected $casts = [
        'is_default' => 'boolean',
        'kwh_per_100km' => 'decimal:2',
        'essence_l_per_100km' => 'decimal:2',
        'diesel_l_per_100km' => 'decimal:2',
    ];

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }

    public function makeDefault(): void
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
