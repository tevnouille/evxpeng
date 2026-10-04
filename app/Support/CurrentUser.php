<?php

namespace App\Support;

use App\Models\User;

/**
 * Utilisateur de la requete en cours.
 *
 * Ce porteur est pose une fois par le middleware (session ouverte) et sert de reference unique au cloisonnement des
 * donnees (voir le trait BelongsToUser).
 *
 * Etat statique assume : PHP-FPM traite une requete par processus. Sous un
 * serveur applicatif persistant (Octane), il faudrait le remettre a zero entre
 * deux requetes.
 */
class CurrentUser
{
    private static ?User $user = null;

    public static function set(?User $user): void
    {
        self::$user = $user;
    }

    public static function get(): ?User
    {
        return self::$user;
    }

    /**
     * Identifiant de l'utilisateur, ou null hors requete web (commandes
     * planifiees, tinker). C'est ce null qui desactive le cloisonnement en
     * console : la telemetrie doit pouvoir interroger les vehicules de tout le
     * monde.
     */
    public static function id(): ?int
    {
        return self::$user?->id;
    }
}
