<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Connexion par email et mot de passe. */
class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('charging-sessions.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::firstWhere('email', $data['email']);

        // Meme message pour un email inconnu, un mauvais mot de passe ou un
        // compte desactive : on ne revele pas quels comptes existent.
        if ($user === null || $user->password === null
            || ! Hash::check($data['password'], $user->password)
            || $user->approved_at === null) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Identifiants incorrects.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('charging-sessions.index'));
    }
}
