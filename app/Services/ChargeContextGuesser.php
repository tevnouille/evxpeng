<?php

namespace App\Services;

use App\Models\ChargingSession;
use App\Models\Location;

/**
 * Devine le contexte d'une recharge (lieu, fournisseur, puissance) a partir de la
 * position ou elle a eu lieu.
 *
 * Le lieu vient de sa proximite avec une localisation connue ; le fournisseur et
 * la puissance viennent de ce qui a ete saisi le plus souvent a cet endroit. Rien
 * n'est invente : sans localisation proche ou sans historique, les champs
 * restent vides et l'utilisateur choisit.
 */
class ChargeContextGuesser
{
    /**
     * Au-dela, on considere qu'il s'agit d'un autre lieu. Large assez pour couvrir
     * un parking et ses imprecisions GPS, court assez pour distinguer deux bornes
     * d'une meme ville.
     */
    private const RADIUS_METERS = 400;

    /**
     * @return array{location_id: int|null, provider_id: int|null, power_rating_id: int|null, location_name: string|null, distance_m: int|null}
     */
    public function guess(?float $latitude, ?float $longitude): array
    {
        $empty = [
            'location_id' => null,
            'provider_id' => null,
            'power_rating_id' => null,
            'location_name' => null,
            'distance_m' => null,
        ];

        if ($latitude === null || $longitude === null) {
            return $empty;
        }

        $nearest = null;
        $nearestDistance = null;

        foreach (Location::whereNotNull('latitude')->whereNotNull('longitude')->get() as $location) {
            $distance = $this->distanceMeters($latitude, $longitude, (float) $location->latitude, (float) $location->longitude);

            if ($distance <= self::RADIUS_METERS && ($nearestDistance === null || $distance < $nearestDistance)) {
                $nearest = $location;
                $nearestDistance = $distance;
            }
        }

        if ($nearest === null) {
            return $empty;
        }

        return array_merge($empty, [
            'location_id' => $nearest->id,
            'location_name' => $nearest->name,
            'distance_m' => (int) round($nearestDistance),
        ], $this->habitsAt($nearest->id));
    }

    /**
     * Couple fournisseur / puissance le plus souvent saisi a cet endroit. A egalite,
     * le plus recent l'emporte : un changement de borne doit finir par primer sur
     * l'habitude ancienne.
     *
     * @return array{provider_id?: int, power_rating_id?: int}
     */
    private function habitsAt(int $locationId): array
    {
        $sessions = ChargingSession::where('location_id', $locationId)
            ->whereNotNull('provider_id')
            ->whereNotNull('power_rating_id')
            ->orderByDesc('session_date')
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $best = $sessions
            ->groupBy(fn ($session) => $session->provider_id.'/'.$session->power_rating_id)
            ->sortByDesc(fn ($group) => $group->count())
            ->first();

        return [
            'provider_id' => $best->first()->provider_id,
            'power_rating_id' => $best->first()->power_rating_id,
        ];
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
