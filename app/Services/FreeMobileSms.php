<?php

namespace App\Services;

use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de SMS via l'API Free Mobile.
 *
 * L'API n'accepte que GET (le POST est refuse) et ne repond rien d'autre qu'un
 * code HTTP. Chaque tentative est journalisee en base : c'est le seul moyen de
 * savoir apres coup pourquoi un SMS attendu n'est pas arrive.
 *
 * Les identifiants appartiennent a l'utilisateur, pas a l'application : depuis
 * l'ouverture a plusieurs comptes, une alerte doit partir sur le telephone du
 * proprietaire du vehicule et sur aucun autre. Il n'y a donc pas de repli sur un
 * compte commun — sans identifiants, pas de SMS.
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

    private ?User $user = null;

    public function forUser(User $user): self
    {
        $clone = clone $this;
        $clone->user = $user;

        return $clone;
    }

    public function configured(): bool
    {
        return filled($this->user?->free_mobile_user) && filled($this->user?->free_mobile_password);
    }

    public function send(string $message): bool
    {
        if ($this->user === null) {
            Log::warning('SMS Free Mobile : aucun destinataire, appeler forUser() d\'abord.');

            return false;
        }

        if (! $this->configured()) {
            $this->record($message, false, null, 'Identifiants Free Mobile absents du compte');

            return false;
        }

        try {
            $response = Http::timeout(15)->get(self::ENDPOINT, [
                'user' => $this->user->free_mobile_user,
                'pass' => $this->user->free_mobile_password,
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
        $sms = new SmsMessage([
            'message' => $message,
            'delivered' => $delivered,
            'http_status' => $status,
            'failure_reason' => $reason,
        ]);

        // Affectation explicite : l'envoi part le plus souvent d'une commande
        // planifiee, ou aucun utilisateur courant n'est pose.
        $sms->user_id = $this->user->id;
        $sms->save();
    }
}
