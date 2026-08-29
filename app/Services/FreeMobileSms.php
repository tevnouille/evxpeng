<?php

namespace App\Services;

use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de SMS via l'API Free Mobile.
 *
 * L'API n'accepte que GET (le POST est refuse) et ne repond rien d'autre qu'un
 * code HTTP. Chaque tentative est journalisee en base : c'est le seul moyen de
 * savoir apres coup pourquoi un SMS attendu n'est pas arrive.
 */
class FreeMobileSms
{
    private const ENDPOINT = 'https://smsapi.free-mobile.fr/sendmsg';

    /** Codes documentes par Free. */
    private const REASONS = [
        400 => 'Paramètre manquant',
        402 => 'Trop de SMS envoyés (quota atteint)',
        403 => 'Service non activé ou identifiants invalides',
        500 => 'Erreur du serveur Free',
    ];

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
            $this->record($message, false, null, 'Identifiants absents du .env');

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
            $this->record($message, false, null, 'Appel impossible : '.$e->getMessage());

            return false;
        }

        if ($response->successful()) {
            $this->record($message, true, $response->status(), null);

            return true;
        }

        $reason = self::REASONS[$response->status()] ?? 'Refus inattendu';

        Log::warning('SMS Free Mobile : refus', ['status' => $response->status(), 'raison' => $reason]);
        $this->record($message, false, $response->status(), $reason);

        return false;
    }

    private function record(string $message, bool $delivered, ?int $status, ?string $reason): void
    {
        SmsMessage::create([
            'message' => $message,
            'delivered' => $delivered,
            'http_status' => $status,
            'failure_reason' => $reason,
        ]);
    }
}
