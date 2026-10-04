<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Petit client JSON commun aux services publics utilises par le planificateur.
 *
 * Il n'existe que pour une raison : ces services (OSRM de demonstration,
 * data.gouv, Nominatim) renvoient une 403 aux clients qui ne s'annoncent pas.
 * Guzzle n'envoie aucun User-Agent par defaut, ce qui rendait les appels
 * silencieusement impossibles depuis le conteneur.
 */
class HttpUserAgent
{
    private const AGENT = 'EV-Recharges (planificateur d\'itineraire, usage personnel)';

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    public function get(string $url, array $query = [], int $timeout = 20): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::AGENT])
                ->timeout($timeout)
                ->get($url, $query);
        } catch (\Throwable $e) {
            Log::warning('Appel HTTP impossible', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Reponse HTTP inattendue', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }
}
