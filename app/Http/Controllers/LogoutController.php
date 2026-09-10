<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Deconnexion.
 *
 * L'application n'a pas de session a elle : l'identite arrive dans l'en-tete
 * pose par la passerelle passkey, a chaque requete. Il n'y a donc rien a
 * detruire ici — la seule chose qui existe est la session de la passerelle, et
 * se deconnecter revient a l'y detruire.
 *
 * Passer par une route interne plutot que par un lien direct vers la passerelle
 * garde son adresse dans la configuration, au lieu de la recopier dans chaque
 * vue qui propose le lien.
 */
class LogoutController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        // `away()` et non `to()` : la cible est un autre domaine, Laravel ne
        // doit pas la prefixer de l'URL de l'application.
        return redirect()->away(rtrim((string) config('services.passkey.url'), '/').'/logout.php');
    }
}
