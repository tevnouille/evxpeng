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
     * Lance l'autorisation FordConnect. Le portail developer.ford.com ne
     * fournit pas de lien de liaison de compte directement accessible (page
     * verifiee le 22/09/2026) : cette methode reconstruit l'URL a partir du
     * point d'entree Azure AD B2C standard (le meme tenant/police que
     * services.ford.token_url, endpoint /authorize au lieu de /token -- motif
     * standard de la plateforme Microsoft identity, pas une valeur propre a
     * Ford). A verifier a l'usage : si Ford exige un chemin different pour ce
     * client_id, la page de connexion Ford elle-meme renverra une erreur
     * explicite plutot qu'un echec silencieux.
     */
    public function authorize(): RedirectResponse
    {
        if (! config('services.ford.client_id') || ! config('services.ford.redirect_uri')) {
            return redirect()->route('my-vehicle.index')
                ->with('error', "Ford : client_id ou redirect_uri non configures (voir .env).");
        }

        $authorizeUrl = str_replace('/oauth2/v2.0/token', '/oauth2/v2.0/authorize', config('services.ford.token_url'));

        $query = http_build_query([
            'client_id' => config('services.ford.client_id'),
            'redirect_uri' => config('services.ford.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid offline_access',
            'state' => Str::random(32),
        ]);

        return redirect($authorizeUrl.'&'.$query);
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
            'scope' => 'openid offline_access',
        ]);

        if (! $reponse->successful() || ! $reponse->json('access_token')) {
            report(new \RuntimeException('Ford OAuth callback : echange du code echoue - '.$reponse->body()));

            return redirect()->route('my-vehicle.index')
                ->with('error', "Echange du code Ford échoué (HTTP {$reponse->status()}) : {$reponse->body()}");
        }

        $donnees = $reponse->json();

        FordOAuthToken::updateOrCreate(
            ['vin' => config('services.ford.vin')],
            [
                'access_token' => $donnees['access_token'],
                'refresh_token' => $donnees['refresh_token'],
                // 3600 s par defaut si Ford omet expires_in : marge prudente,
                // le prochain appel rafraichira de toute facon si perime.
                'expires_at' => now()->addSeconds((int) ($donnees['expires_in'] ?? 3600)),
            ]
        );

        return redirect()->route('my-vehicle.index')
            ->with('success', 'Autorisation Ford enregistrée : la synchronisation peut démarrer.');
    }
}
