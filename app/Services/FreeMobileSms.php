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

    /**
     * Free met parfois plus de quinze secondes a repondre, alors meme que le
     * SMS part. Un delai trop court transforme un envoi reussi en echec.
     */
    private const TIMEOUT_SECONDS = 30;

    /** Marge sous la taille de la colonne, pour ne jamais faire echouer l'insertion. */
    private const MAX_REASON = 240;

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
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get(self::ENDPOINT, [
                'user' => $this->user->free_mobile_user,
                'pass' => $this->user->free_mobile_password,
                'msg' => $message,
            ]);
        } catch (\Throwable $e) {
            $reason = $this->reason($e);

            Log::warning('SMS Free Mobile : appel impossible', ['raison' => $reason]);
            $this->record($message, false, null, $reason);

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

    /**
     * Motif d'echec lisible et sans secret.
     *
     * Le message d'exception de Guzzle contient l'URL appelee, donc
     * l'identifiant et la cle Free en clair : les enregistrer en base les
     * afficherait ensuite dans le journal des envois. On coupe avant l'URL.
     *
     * Un delai depasse ne veut pas dire que le SMS n'est pas parti : Free repond
     * parfois apres coup. Le libelle le dit, plutot que d'affirmer un echec.
     */
    private function reason(\Throwable $e): string
    {
        $message = preg_replace('/\s*(for|see)\s+https?:\/\/\S+/i', '', $e->getMessage()) ?? '';
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        if (str_contains(strtolower($message), 'timed out') || str_contains(strtolower($message), 'timeout')) {
            return mb_substr('Delai depasse cote application ; le SMS a pu partir malgre tout. '.$message, 0, self::MAX_REASON);
        }

        return mb_substr('Appel impossible : '.$message, 0, self::MAX_REASON);
    }

    /**
     * Journalise la tentative.
     *
     * Encapsule : une trace qui echoue ne doit jamais casser la page. C'est
     * exactement ce qui s'etait produit — un motif d'echec trop long faisait
     * remonter une erreur SQL en 500, et l'envoi disparaissait du journal.
     */
    private function record(string $message, bool $delivered, ?int $status, ?string $reason): void
    {
        try {
            $sms = new SmsMessage([
                'message' => $message,
                'delivered' => $delivered,
                'http_status' => $status,
                'failure_reason' => $reason === null ? null : mb_substr($reason, 0, self::MAX_REASON),
            ]);

            // Affectation explicite : l'envoi part le plus souvent d'une commande
            // planifiee, ou aucun utilisateur courant n'est pose.
            $sms->user_id = $this->user->id;
            $sms->save();
        } catch (\Throwable $e) {
            Log::error('SMS Free Mobile : journalisation impossible', ['message' => $e->getMessage()]);
        }
    }
}
