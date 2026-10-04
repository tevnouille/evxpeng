<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige une session ouverte (login / mot de passe) et pose l'utilisateur
 * courant, qui sert de reference au cloisonnement des donnees (BelongsToUser).
 */
class IdentifyUser
{
    /**
     * Chemins servis sans session, en minuscules.
     *
     * `infocar` : l'etat du vehicule pour le navigateur embarque de la voiture,
     * ou un formulaire de connexion n'a pas sa place (la page a son propre code
     * d'acces).
     *
     * `login` : le formulaire de connexion lui-meme.
     *
     * `up` : la sonde de sante de Laravel.
     */
    private const PUBLIC_PATHS = ['infocar', 'login', 'up'];

    /**
     * Routes nommees servies sans session, en plus de PUBLIC_PATHS.
     *
     * `position-shares.show` : le lien de partage de position. Verifie par nom
     * de route et non par chemin, puisque le chemin est un token genere. La
     * protection est le token lui-meme, aleatoire et non devinable.
     */
    private const PUBLIC_ROUTES = ['position-shares.show'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(strtolower(trim($request->path(), '/')), self::PUBLIC_PATHS, true)
            || $request->routeIs(...self::PUBLIC_ROUTES)) {
            return $next($request);
        }

        $user = Auth::user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        // Compte desactive depuis l'ouverture de la session : on la ferme.
        if ($user->approved_at === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Ce compte est désactivé.');
        }

        $this->touchLastSeen($user);

        CurrentUser::set($user);

        return $next($request);
    }

    /**
     * Une ecriture par minute au plus : la date de derniere visite sert a
     * reperer un compte inutilise, pas a tracer la navigation.
     */
    private function touchLastSeen($user): void
    {
        if ($user->last_seen_at !== null && $user->last_seen_at->diffInSeconds(now()) < 60) {
            return;
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
    }
}
