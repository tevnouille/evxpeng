<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UserProvisioner;
use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifie l'utilisateur a partir de l'en-tete pose par la passerelle passkey.
 *
 * La passerelle ecrase systematiquement cet en-tete avec la valeur issue de la
 * session SSO (`proxy_set_header`), donc un client ne peut pas se l'inventer.
 * L'application n'est joignable que par elle.
 */
class IdentifyUser
{
    public const HEADER = 'X-SSO-Email';

    /**
     * Chemins servis sans identite, en minuscules.
     *
     * `infocar` : l'etat du vehicule pour le navigateur embarque de la voiture,
     * ou une ceremonie passkey n'a pas sa place. La passerelle laisse passer
     * cette adresse et vide l'en-tete d'identite au passage, de sorte qu'elle
     * ne puisse pas servir a se declarer proprietaire d'un compte.
     *
     * `deconnexion` : la passerelle exige toujours un passkey pour y acceder,
     * c'est bien ici que l'identite n'est pas requise. Sans cela, un compte
     * refuse plus bas (en attente d'autorisation) verrait un 403 sans aucun
     * moyen de se deconnecter pour changer de compte — l'impasse.
     *
     * L'exception vit ici, dans le middleware qui l'accorde, plutot que dans un
     * groupe de routes a part : on la lit au meme endroit que la regle.
     */
    private const PUBLIC_PATHS = ['infocar', 'deconnexion'];

    /**
     * Routes nommees servies sans identite, en plus de PUBLIC_PATHS.
     *
     * `position-shares.show` : le lien de partage de position, ouvert sur le
     * domaine dedie s.lolinux.fr (docker/share/README.md), qui ne passe pas
     * par la passerelle passkey. Verifie par nom de route et non par chemin,
     * puisque le chemin est un token genere (`/{token}`) qui ne peut pas
     * figurer dans PUBLIC_PATHS. Le middleware `web` s'execute apres le
     * routage, `Request::route()` est donc deja resolu ici. La protection
     * reste le token lui-meme, aleatoire et non devinable — comme tout lien
     * de partage.
     */
    private const PUBLIC_ROUTES = ['position-shares.show'];

    public function __construct(private readonly UserProvisioner $provisioner)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Comparaison en minuscules : un clavier de voiture n'est pas un clavier.
        if (in_array(strtolower(trim($request->path(), '/')), self::PUBLIC_PATHS, true)) {
            return $next($request);
        }

        if ($request->routeIs(...self::PUBLIC_ROUTES)) {
            return $next($request);
        }

        $email = trim((string) $request->header(self::HEADER));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(403, 'Identite absente : cette application doit etre atteinte via la passerelle passkey.');
        }

        $user = User::firstWhere('email', $email) ?? $this->provisioner->create($email);

        // Le SSO passkey est partage avec les autres services du domaine :
        // en posseder un ne suffit pas a entrer ici. Le compte est bien cree,
        // pour que l'administrateur voie la demande, mais reste ferme.
        abort_if($user->approved_at === null, 403,
            'Votre compte est en attente d\'autorisation sur cette application. '
            .'Pour repartir avec un autre compte : /deconnexion');

        $this->touchLastSeen($user);

        CurrentUser::set($user);
        // Pose aussi l'utilisateur cote framework, pour que `auth()->user()`
        // reponde dans les vues sans passer par le porteur.
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * Une ecriture par minute au plus : la date de derniere visite sert a
     * reperer un compte inutilise, pas a tracer la navigation.
     */
    private function touchLastSeen(User $user): void
    {
        if ($user->last_seen_at !== null && $user->last_seen_at->diffInSeconds(now()) < 60) {
            return;
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
    }
}
