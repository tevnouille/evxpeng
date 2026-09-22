<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jeton OAuth FordConnect pour un vehicule (identifie par son VIN).
 *
 * access_token/refresh_token chiffres (cast `encrypted`) : comme le mot de
 * passe Free Mobile de App\Models\User, ils doivent pouvoir etre relus pour
 * appeler l'API, d'ou `encrypted` et non `hashed`. Illisibles si APP_KEY change.
 */
class FordOAuthToken extends Model
{
    protected $table = 'ford_oauth_tokens';

    protected $fillable = [
        'vin',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];
}
