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

    public function __construct(private readonly UserProvisioner $provisioner)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $email = trim((string) $request->header(self::HEADER));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(403, 'Identite absente : cette application doit etre atteinte via la passerelle passkey.');
        }

        $user = User::firstWhere('email', $email) ?? $this->provisioner->create($email);

        // Le SSO passkey est partage avec les autres services du domaine :
        // en posseder un ne suffit pas a entrer ici. Le compte est bien cree,
        // pour que l'administrateur voie la demande, mais reste ferme.
        abort_if($user->approved_at === null, 403,
            'Votre compte est en attente d\'autorisation sur cette application.');

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
