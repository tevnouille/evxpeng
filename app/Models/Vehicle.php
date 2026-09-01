<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = ['name', 'charging_curve', 'abrp_token',
        'mqtt_client_id', 'is_default', 'kwh_per_100km', 'essence_l_per_100km', 'diesel_l_per_100km'];

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

    public function telemetries(): HasMany
    {
        return $this->hasMany(VehicleTelemetry::class);
    }

    /**
     * Derniere mesure remontee par ABRP, la plus recente selon l'horodatage du
     * constructeur (et non selon la date d'insertion chez nous).
     */
    public function latestTelemetry(): HasOne
    {
        return $this->hasOne(VehicleTelemetry::class)->latestOfMany('recorded_at');
    }

    public function makeDefault(): void
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
