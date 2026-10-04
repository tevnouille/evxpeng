<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Lien unique et temporaire vers la position d'un vehicule.
 *
 * Le token est genere ici, jamais fillable : venir d'un champ de formulaire
 * permettrait de choisir son propre lien, donc d'en deviner un autre.
 */
class PositionShare extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = ['vehicle_id', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $share) {
            $share->token ??= Str::random(40);
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function url(): string
    {
        $domain = config('services.position_share.domain');

        return $domain
            ? 'https://'.$domain.'/'.$this->token
            : route('position-shares.show', $this->token);
    }
}
