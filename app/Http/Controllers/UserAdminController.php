<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserProvisioner;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestion des comptes.
 *
 * L'authentification reste le passkey : on n'y cree ni mot de passe ni identite,
 * on decide seulement qui, parmi les porteurs de passkey du domaine, a le droit
 * d'entrer ici et de voir ses propres donnees.
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

    /**
     * Autorise un email avant meme sa premiere visite : la personne se connecte
     * ensuite avec son passkey et trouve l'application prete.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        $user = $this->provisioner->create($data['email']);
        $user->approved_at = now();
        $user->save();

        return redirect()->route('reference-data.users.index')
            ->with('success', $data['email'].' est autorisé. Il ne lui reste qu\'à se connecter avec son passkey.');
    }

    public function approve(User $user): RedirectResponse
    {
        $user->approved_at = now();
        $user->save();

        return redirect()->route('reference-data.users.index')->with('success', $user->email.' est autorisé.');
    }

    public function revoke(User $user): RedirectResponse
    {
        if ($this->isMyself($user)) {
            return redirect()->route('reference-data.users.index')
                ->with('error', 'Retirer sa propre autorisation vous enfermerait dehors.');
        }

        $user->approved_at = null;
        $user->save();

        return redirect()->route('reference-data.users.index')
            ->with('success', 'Accès retiré à '.$user->email.'. Ses données sont conservées.');
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
