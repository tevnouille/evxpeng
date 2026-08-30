<?php

namespace App\Services;

use App\Models\PowerRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creation d'un compte a la premiere visite.
 *
 * Il n'y a pas d'inscription : quiconque franchit la passerelle passkey obtient
 * un compte. Les listes de reference etant privees a chacun, un nouvel arrivant
 * repart de zero, a l'exception des puissances de borne — des valeurs physiques
 * qu'il aurait a ressaisir a l'identique.
 */
class UserProvisioner
{
    /** Memes valeurs que PowerRatingSeeder, qui amorce le premier compte. */
    private const DEFAULT_POWER_RATINGS = [7, 11, 22, 50, 150, 300, 400];

    public function create(string $email): User
    {
        return DB::transaction(function () use ($email) {
            $user = User::create([
                'name' => Str::before($email, '@'),
                'email' => $email,
                // Colonne heritee du squelette Laravel : personne ne se connecte
                // par mot de passe ici, l'authentification est le passkey.
                'password' => null,
            ]);

            foreach (self::DEFAULT_POWER_RATINGS as $kw) {
                // `user_id` n'est jamais assignable en masse : le proprietaire
                // d'une ligne ne doit pas pouvoir venir d'un champ de formulaire.
                $rating = new PowerRating(['kw' => $kw]);
                $rating->user_id = $user->id;
                $rating->save();
            }

            return $user;
        });
    }
}
