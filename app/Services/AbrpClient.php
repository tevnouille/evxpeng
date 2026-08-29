<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client de l'API telemetrie Iternio (A Better Routeplanner).
 *
 * ABRP ne parle pas directement a la voiture : c'est Enode qui interroge le
 * cloud du constructeur, puis ABRP nous expose la derniere mesure connue.
 * Deux consequences a garder en tete :
 *  - la donnee est rafraichie environ toutes les heures a l'arret, plus
 *    souvent en charge ;
 *  - le backend traite la telemetrie par lots avec 60 s de decalage.
 * Inutile donc d'interroger l'API plus vite que le quart d'heure.
 */
class AbrpClient
{
    private readonly ?string $apiKey;

    private readonly string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.abrp.key');
        $this->baseUrl = rtrim((string) config('services.abrp.base_url'), '/');
    }

    public function configured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Derniere telemetrie connue pour le vehicule identifie par ce token.
     *
     * @return array{telemetry: array<string, mixed>, timestamp: string, ...}|null
     *         null si la cle manque, si l'appel echoue, ou si ABRP n'a encore
     *         rien recu pour ce vehicule.
     */
    public function latestTelemetry(string $token): ?array
    {
        return $this->get('get_telemetry', $token, fn (array $result) => empty($result['telemetry']) ? null : $result);
    }

    /**
     * Objectif de charge du plan de route en cours, en pourcent.
     */
    public function nextCharge(string $token): ?float
    {
        return $this->get('get_next_charge', $token, function (array $result) {
            return isset($result['next_charge']) ? (float) $result['next_charge'] : null;
        });
    }

    /**
     * @param  callable(array<string, mixed>): mixed  $extract
     */
    private function get(string $endpoint, string $token, callable $extract): mixed
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => 'APIKEY '.$this->apiKey])
                ->get("{$this->baseUrl}/tlm/{$endpoint}", ['token' => $token]);
        } catch (\Throwable $e) {
            Log::warning("ABRP {$endpoint} : appel impossible", ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            // 401 "Unauthorized Key" = cle API invalide, 401 "Unauthorized Token"
            // = token du vehicule invalide. Le corps le precise, on le journalise.
            Log::warning("ABRP {$endpoint} : HTTP {$response->status()}", ['body' => $response->body()]);

            return null;
        }

        $payload = $response->json();

        // L'API repond 200 meme sur erreur applicative : c'est "status" qui tranche.
        if (! is_array($payload) || ($payload['status'] ?? null) !== 'ok' || ! is_array($payload['result'] ?? null)) {
            Log::warning("ABRP {$endpoint} : reponse inattendue", ['body' => $response->body()]);

            return null;
        }

        return $extract($payload['result']);
    }
}
