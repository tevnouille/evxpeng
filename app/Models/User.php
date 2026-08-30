<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'free_mobile_password'])]
class User extends Authenticatable
{
    /** Memorisation par instance : la navigation pose la question a chaque page. */
    private ?bool $hasTelemetry = null;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Donnees possedees par le compte.
     *
     * Le scope global est retire : ces relations servent a compter ce que
     * possede *un* compte donne, pas celui de la requete en cours. Sans cela,
     * la page de gestion afficherait zero partout sauf pour soi.
     */
    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class)->withoutGlobalScope('user');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->withoutGlobalScope('user');
    }

    public function favoriteRoutes(): HasMany
    {
        return $this->hasMany(FavoriteRoute::class)->withoutGlobalScope('user');
    }

    /**
     * Ce compte dispose-t-il d'une telemetrie ?
     *
     * Autrement dit : au moins un vehicule relie a A Better Routeplanner, ce qui
     * suppose un abonnement ABRP Premium a soi. C'est ce qui conditionne l'acces
     * aux pages "Ma voiture" et "Deplacements".
     *
     * Le scope global est retire explicitement : la question porte sur ce
     * compte-ci, qui n'est pas forcement celui de la requete.
     */
    public function hasTelemetry(): bool
    {
        return $this->hasTelemetry ??= Vehicle::withoutGlobalScope('user')
            ->where('user_id', $this->id)
            ->whereNotNull('abrp_token')
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Chiffree en base : c'est une cle d'API, pas un mot de passe a
            // verifier, il faut pouvoir la relire pour appeler Free Mobile.
            'free_mobile_password' => 'encrypted',
            'approved_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_admin' => 'boolean',
            'show_fuel_equivalent' => 'boolean',
        ];
    }
}
