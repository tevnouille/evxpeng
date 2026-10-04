<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserProvisioner;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Gestion des comptes, reservee aux administrateurs : creation, role,
 * mot de passe, activation et suppression.
 */
class UserAdminController extends Controller
{
    public function __construct(private readonly UserProvisioner $provisioner)
    {
    }

    public function index(): View
    {
        return view('reference_data.users', [
            'users' => User::withCount([
                'chargingSessions',
                'vehicles',
                'favoriteRoutes',
            ])->orderBy('email')->get(),
            'me' => CurrentUser::get(),
        ]);
    }

    /** Cree un compte avec son mot de passe initial, immediatement actif. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(12), 'max:255'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $this->provisioner->create($data['email'], $data['password'], $request->boolean('is_admin'));

        return redirect()->route('reference-data.users.index')
            ->with('success', 'Compte '.$data['email'].' créé.');
    }

    /** Promeut ou retire le role administrateur. */
    public function role(Request $request, User $user): RedirectResponse
    {
        $admin = $request->boolean('is_admin');

        if (! $admin && $this->isMyself($user)) {
            return redirect()->route('reference-data.users.index')
                ->with('error', 'Vous ne pouvez pas retirer votre propre rôle administrateur.');
        }

        $user->is_admin = $admin;
        $user->save();

        return redirect()->route('reference-data.users.index')
            ->with('success', $user->email.($admin ? ' est administrateur.' : ' n\'est plus administrateur.'));
    }

    /** Donne ou retire l'acces aux donnees Xpeng. */
    public function xpeng(Request $request, User $user): RedirectResponse
    {
        $user->xpeng_access = $request->boolean('xpeng_access');
        $user->save();

        return redirect()->route('reference-data.users.index')
            ->with('success', 'Accès « Données Xpeng » '.($user->xpeng_access ? 'accordé à ' : 'retiré à ').$user->email.'.');
    }

    /** Definit un nouveau mot de passe pour le compte. */
    public function password(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', Password::min(12), 'max:255'],
        ]);

        $user->password = $data['password'];
        $user->save();

        return redirect()->route('reference-data.users.index')
            ->with('success', 'Mot de passe de '.$user->email.' modifié.');
    }

    public function approve(User $user): RedirectResponse
    {
        $user->approved_at = now();
        $user->save();

        return redirect()->route('reference-data.users.index')->with('success', $user->email.' est activé.');
    }

    public function revoke(User $user): RedirectResponse
    {
        if ($this->isMyself($user)) {
            return redirect()->route('reference-data.users.index')
                ->with('error', 'Désactiver son propre compte vous enfermerait dehors.');
        }

        $user->approved_at = null;
        $user->save();

        return redirect()->route('reference-data.users.index')
            ->with('success', 'Compte de '.$user->email.' désactivé. Ses données sont conservées.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($this->isMyself($user)) {
            return redirect()->route('reference-data.users.index')
                ->with('error', 'Impossible de supprimer son propre compte.');
        }

        $email = $user->email;
        // Les cles etrangeres sont en cascade : recharges, vehicules, listes de
        // reference et trajets partent avec le compte.
        $user->delete();

        return redirect()->route('reference-data.users.index')
            ->with('success', 'Compte '.$email.' supprimé, avec toutes ses données.');
    }

    private function isMyself(User $user): bool
    {
        return $user->id === CurrentUser::id();
    }
}
