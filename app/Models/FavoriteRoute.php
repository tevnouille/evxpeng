<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FavoriteRoute extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'copied_from',
        'from_label', 'from_lat', 'from_lon',
        'to_label', 'to_lat', 'to_lon',
        'min_power_kw', 'max_detour_km', 'networks',
        'distance_km', 'duration_minutes', 'geometry',
    ];

    protected $casts = [
        'from_lat' => 'float',
        'from_lon' => 'float',
        'to_lat' => 'float',
        'to_lon' => 'float',
        'min_power_kw' => 'float',
        'max_detour_km' => 'float',
        'distance_km' => 'float',
        'duration_minutes' => 'integer',
        'geometry' => 'array',
        'networks' => 'array',
    ];

    public function stations(): HasMany
    {
        return $this->hasMany(FavoriteRouteStation::class)->orderBy('km');
    }

    /**
     * Itineraire complet dans Google Maps, bornes retenues en etapes.
     *
     * Google plafonne a neuf etapes intermediaires ; au-dela le lien est refuse,
     * on garde donc les premieres.
     */
    public function googleMapsUrl(): string
    {
        $waypoints = $this->stations
            ->take(9)
            ->map(fn ($station) => $station->lat.','.$station->lon)
            ->implode('|');

        return 'https://www.google.com/maps/dir/?'.http_build_query(array_filter([
            'api' => '1',
            'origin' => $this->from_lat.','.$this->from_lon,
            'destination' => $this->to_lat.','.$this->to_lon,
            'waypoints' => $waypoints,
            'travelmode' => 'driving',
        ]));
    }
}
