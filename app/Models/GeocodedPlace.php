<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adresse resolue pour une position arrondie.
 *
 * Aucun cloisonnement par utilisateur : une adresse postale n'appartient a
 * personne, et deux comptes qui passent au meme endroit ont tout interet a
 * partager la resolution plutot qu'a la redemander.
 */
class GeocodedPlace extends Model
{
    protected $fillable = ['lat', 'lon', 'label', 'city', 'postcode', 'distance_m'];

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
        'distance_m' => 'integer',
    ];
}
