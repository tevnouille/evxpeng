<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteRouteStation extends Model
{
    protected $fillable = [
        'charging_station_id', 'name', 'network', 'address', 'city',
        'lat', 'lon', 'power_kw', 'km',
    ];

    protected $casts = [
        'lat' => 'float',
        'lon' => 'float',
        'power_kw' => 'float',
        'km' => 'float',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(FavoriteRoute::class, 'favorite_route_id');
    }

    /** Fiche de la borne, pas un itineraire : c'est l'adresse qu'on veut voir. */
    public function googleMapsUrl(): string
    {
        return 'https://www.google.com/maps/search/?'.http_build_query([
            'api' => '1',
            'query' => $this->lat.','.$this->lon,
        ]);
    }

    /** Waze n'accepte qu'une destination a la fois, d'ou un lien par borne. */
    public function wazeUrl(): string
    {
        return 'https://www.waze.com/ul?'.http_build_query([
            'll' => $this->lat.','.$this->lon,
            'navigate' => 'yes',
        ]);
    }
}
