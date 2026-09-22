<?php

namespace App\Services;

use App\Models\FordOAuthToken;
use Illuminate\Support\Facades\Http;

/**
 * Client pour l'API FordConnect Query (App\Http\Controllers\FordAuthController
 * gere l'autorisation initiale ; ce client gere le rafraichissement du jeton
 * et les appels de lecture).
 *
 * Particularite constatee le 22/09/2026 en testant le flux d'authentification :
 * la politique B2C utilisee (B2C_1A_FCON_AUTHORIZE, scope openid+offline_access)
 * ne renvoie jamais de champ `access_token`, seulement `id_token` -- verifie en
 * l'utilisant en Bearer sur /v1/garage, qui repond avec les donnees du vehicule.
 * C'est donc lui qui fait office de jeton d'acces, malgre son nom, aussi bien a
 * l'autorisation initiale qu'au rafraichissement.
 */
class FordClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $tokenUrl,
        private readonly string $applicationId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $vin,
    ) {
    }

    /** @return array<string, mixed> */
    public function garage(): array
    {
        return $this->get('/v1/garage');
    }

    /** @return array<string, mixed> */
    public function telemetry(): array
    {
        return $this->get('/v1/telemetry');
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        $reponse = Http::withToken($this->validAccessToken())
            ->withHeaders(['Application-Id' => $this->applicationId])
            ->get($this->baseUrl.$path);

        if (! $reponse->successful()) {
            throw new \RuntimeException("Ford API $path : HTTP {$reponse->status()} - {$reponse->body()}");
        }

        return $reponse->json();
    }

    private function validAccessToken(): string
    {
        $token = FordOAuthToken::where('vin', $this->vin)->first();

        if (! $token) {
            throw new \RuntimeException("Ford : aucune autorisation enregistree pour $this->vin (voir /ma-voiture/donnees-ford/autoriser).");
        }

        // Marge de 2 minutes : le jeton ne doit pas perimer entre cette
        // verification et l'appel qui suit.
        if ($token->expires_at->subMinutes(2)->isFuture()) {
            return $token->access_token;
        }

        return $this->refresh($token);
    }

    private function refresh(FordOAuthToken $token): string
    {
        $reponse = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $token->refresh_token,
            'scope' => 'openid offline_access',
        ]);

        if (! $reponse->successful() || ! $reponse->json('id_token') || ! $reponse->json('refresh_token')) {
            throw new \RuntimeException('Ford : rafraichissement du jeton echoue - '.$reponse->body());
        }

        $donnees = $reponse->json();

        $token->update([
            'access_token' => $donnees['id_token'],
            'refresh_token' => $donnees['refresh_token'],
            'expires_at' => now()->addSeconds((int) ($donnees['id_token_expires_in'] ?? 1200)),
        ]);

        return $donnees['id_token'];
    }
}
