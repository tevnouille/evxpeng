<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cloisonne un modele par utilisateur.
 *
 * Le filtrage est un scope global plutot qu'un `where` a repeter dans chaque
 * controleur : on ne peut pas l'oublier, et une fuite de donnees entre comptes
 * demanderait de le retirer explicitement.
 *
 * Hors requete web, `CurrentUser::id()` vaut null et le scope ne s'applique
 * pas : `telemetry:poll` et `irve:import` doivent voir toute la base.
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            $id = CurrentUser::id();

            if ($id !== null) {
                // Colonne qualifiee : plusieurs tableaux de bord joignent des
                // tables qui portent elles aussi un user_id.
                $query->where($query->getModel()->getTable().'.user_id', $id);
            }
        });

        static::creating(function ($model) {
            // `??=` et non une affectation seche : une copie de trajet cree
            // deliberement une ligne pour quelqu'un d'autre.
            $model->user_id ??= CurrentUser::id();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
