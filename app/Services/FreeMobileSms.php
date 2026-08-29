<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de SMS via l'API Free Mobile.
 *
 * L'API n'accepte que GET (le POST est refuse) et ne repond rien d'autre qu'un
 * code HTTP : 200 envoye, 400 parametre manquant, 402 trop de SMS envoyes,
 * 403 service non active ou identifiants faux, 500 cote serveur.
 */
class FreeMobileSms
{
    private const ENDPOINT = 'https://smsapi.free-mobile.fr/sendmsg';

    private readonly ?string $user;

    private readonly ?string $password;

    public function __construct()
    {
        $this->user = config('services.free_mobile.user');
        $this->password = config('services.free_mobile.password');
    }

    public function configured(): bool
    {
        return filled($this->user) && filled($this->password);
    }

    public function send(string $message): bool
    {
        if (! $this->configured()) {
            Log::warning('SMS Free Mobile : identifiants absents du .env.');

            return false;
        }

        try {
            $response = Http::timeout(15)->get(self::ENDPOINT, [
                'user' => $this->user,
                'pass' => $this->password,
                'msg' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SMS Free Mobile : appel impossible', ['message' => $e->getMessage()]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::warning('SMS Free Mobile : refus', [
            'status' => $response->status(),
            'sens' => match ($response->status()) {
                400 => 'parametre manquant',
                402 => 'trop de SMS envoyes',
                403 => 'service non active ou identifiants invalides',
                500 => 'erreur serveur Free',
                default => 'inconnu',
            },
        ]);

        return false;
    }
}
