<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Bascule les identifiants Free Mobile du .env vers le compte proprietaire.
 *
 * Ils etaient globaux tant que l'application etait mono-compte. Les laisser en
 * configuration ferait partir les alertes de tout le monde sur le meme
 * telephone : chaque compte porte desormais les siens.
 *
 * Lecture directe de l'environnement : la cle de configuration correspondante a
 * ete supprimee une fois la bascule faite, cette migration doit rester jouable
 * sur une base neuve (ou elle ne trouvera rien, et ne fera rien).
 */
return new class extends Migration
{
    public function up(): void
    {
        $user = env('FREE_MOBILE_USER');
        $password = env('FREE_MOBILE_PASS');

        if (blank($user) || blank($password)) {
            return;
        }

        $owner = User::firstWhere('email', env('EV_OWNER_EMAIL', 'atran@lolinux.org'))
            ?? User::orderBy('id')->first();

        if ($owner === null || filled($owner->free_mobile_user)) {
            return;
        }

        $owner->free_mobile_user = $user;
        $owner->free_mobile_password = $password;
        $owner->save();
    }

    public function down(): void
    {
        // Les identifiants restent dans le .env : rien a restaurer.
    }
};
