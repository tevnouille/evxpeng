<?php

namespace App\Http\Controllers;

use App\Models\FordOAuthToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Point de retour de l'autorisation FordConnect (OAuth2 Azure AD B2C).
 *
 * Ford redirige ici avec un `code` apres que le titulaire du compte
 * Ford/FordPass associe au vehicule ait autorise l'application depuis la
 * page de liaison de compte fournie par Ford (fordconnect.cv.ford.com,
 * distincte de ce depot -- rien ici ne construit cette URL). Ce point
 * d'entree echange le code contre un access_token/refresh_token, stockes en
 * base (App\Models\FordOAuthToken) -- jamais en .env, le refresh_token
 * tournant a chaque utilisation cote Ford (voir la migration).
 */
class FordAuthController extends Controller
{
    /**
     * Lance l'autorisation FordConnect. Point d'entree confirme en pratique
     * le 22/09/2026 (le portail developer.ford.com n'expose ce lien nulle
     * part) : ce n'est pas l'endpoint Azure AD B2C generique, mais une route
     * propre a FordConnect qui gere elle-meme la redirection vers la
     * connexion Ford/FordPass.
     */
    public function authorize(): RedirectResponse
    {
        if (! config('services.ford.client_id') || ! config('services.ford.redirect_uri')) {
            return redirect()->route('my-vehicle.index')
                ->with('error', "Ford : client_id ou redirect_uri non configures (voir .env).");
        }

        $query = http_build_query([
            'client_id' => config('services.ford.client_id'),
            'redirect_uri' => config('services.ford.redirect_uri'),
            'state' => Str::random(32),
            // evData : sans lui, tout /v1/electric/* (autonomie, charge,
            // branchement, plages programmees) repond 403 "Required scopes
            // missing: evData" -- constate le 22/09/2026 sur /v1/telemetry
            // et /v1/electric/departure-times.
            'scope' => 'openid offline_access evData',
        ]);

        return redirect(config('services.ford.authorize_url').'?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('my-vehicle.index')
                ->with('error', 'Autorisation Ford refusée ou annulée : '
                    .$request->query('error_description', $request->query('error')));
        }

        $request->validate(['code' => ['required', 'string']]);

        if (! config('services.ford.client_id') || ! config('services.ford.client_secret')) {
            return redirect()->route('my-vehicle.index')
                ->with('error', "Ford : client_id/client_secret non configures (voir .env), rien a faire.");
        }

        if (! config('services.ford.vin')) {
            return redirect()->route('my-vehicle.index')
                ->with('error', "Ford : FORD_VIN non configure (voir .env), impossible de savoir a quel vehicule rattacher ce jeton.");
        }

        $reponse = Http::asForm()->post(config('services.ford.token_url'), [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.ford.client_id'),
            'client_secret' => config('services.ford.client_secret'),
            'code' => $request->query('code'),
            'redirect_uri' => config('services.ford.redirect_uri'),
            'scope' => 'openid offline_access evData',
        ]);

        // Particularite de cette politique B2C (B2C_1A_FCON_AUTHORIZE, scope
        // openid+offline_access) : elle ne renvoie jamais de champ
        // access_token, seulement id_token -- verifie le 22/09/2026 en
        // testant ce jeton en Bearer sur /fcon-query/v1/garage, qui repond
        // bien avec les donnees du vehicule. C'est donc lui la valeur a
        // utiliser pour les appels a l'API, malgre son nom.
        if (! $reponse->successful() || ! $reponse->json('id_token') || ! $reponse->json('refresh_token')) {
            report(new \RuntimeException('Ford OAuth callback : echange du code echoue - '.$reponse->body()));

            return redirect()->route('my-vehicle.index')
                ->with('error', "Echange du code Ford échoué (HTTP {$reponse->status()}) : {$reponse->body()}");
        }

        $donnees = $reponse->json();

        FordOAuthToken::updateOrCreate(
            ['vin' => config('services.ford.vin')],
            [
                'access_token' => $donnees['id_token'],
                'refresh_token' => $donnees['refresh_token'],
                'expires_at' => now()->addSeconds((int) ($donnees['id_token_expires_in'] ?? 1200)),
            ]
        );

        return redirect()->route('my-vehicle.index')
            ->with('success', 'Autorisation Ford enregistrée : la synchronisation peut démarrer.');
    }
}
