<?php

namespace App\Http\Controllers;

use App\Services\FreeMobileSms;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reglages propres au compte connecte.
 *
 * Pour l'instant les seuls reglages personnels sont les identifiants Free
 * Mobile : les alertes de charge partent sur le telephone du proprietaire du
 * vehicule, jamais sur un compte commun.
 */
class AccountController extends Controller
{
    public function index(): View
    {
        $user = CurrentUser::get();

        return view('account.index', [
            'user' => $user,
            'thresholds' => config('services.charge_alerts.thresholds', []),
            'smsConfigured' => filled($user->free_mobile_user) && filled($user->free_mobile_password),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'free_mobile_user' => ['nullable', 'string', 'max:60'],
            'free_mobile_password' => ['nullable', 'string', 'max:120'],
        ]);

        $user = CurrentUser::get();

        $user->free_mobile_user = $data['free_mobile_user'] ?: null;

        // Champ laisse vide alors qu'un identifiant reste renseigne : on garde la
        // cle existante. C'est le seul moyen de modifier l'identifiant sans avoir
        // a ressaisir une cle qu'on ne peut pas relire a l'ecran.
        if (filled($data['free_mobile_password'])) {
            $user->free_mobile_password = $data['free_mobile_password'];
        } elseif (blank($data['free_mobile_user'])) {
            $user->free_mobile_password = null;
        }

        $user->save();

        return redirect()->route('account.index')->with('success', 'Compte mis à jour.');
    }

    /**
     * Preferences d'affichage.
     *
     * Formulaire distinct de celui des identifiants SMS : une case a cochee
     * absente de la requete vaut "decochee", donc enregistrer l'un ne doit
     * jamais toucher a l'autre.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = CurrentUser::get();
        $user->show_fuel_equivalent = $request->boolean('show_fuel_equivalent');
        $user->save();

        return redirect()->route('account.index')->with('success', 'Préférences enregistrées.');
    }

    /** Les identifiants Free ne se verifient pas autrement qu'en envoyant. */
    public function testSms(FreeMobileSms $sms): RedirectResponse
    {
        $user = CurrentUser::get();

        if ($sms->forUser($user)->send('EV Recharges : test d\'envoi.')) {
            return back()->with('success', 'SMS de test envoyé.');
        }

        return back()->with('error', 'Envoi refusé. Le détail figure dans le journal des envois.');
    }
}
