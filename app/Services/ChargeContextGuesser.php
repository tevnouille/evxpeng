<?php

namespace App\Services;

use App\Models\ChargingSession;
use App\Models\Location;

/**
 * Devine le contexte d'une recharge (lieu, fournisseur, puissance) a partir de la
 * position ou elle a eu lieu.
 *
 * Deux sources, dans cet ordre :
 *
 * 1. Les recharges deja saisies, dont on connait la position exacte. C'est la
 *    plus precise : une ville peut compter plusieurs bornes, et chacune accumule
 *    ses propres saisies. Deux bornes distantes de 800 m dans la meme ville sont
 *    ainsi distinguees, avec chacune son fournisseur et sa puissance.
 *
 * 2. A defaut, les coordonnees saisies a la main sur la localisation. Elles ne
 *    valent que pour un point ; elles servent d'amorce tant qu'aucune recharge
 *    n'a ete enregistree a cet endroit.
 *
 * Rien n'est invente : sans correspondance, les champs restent vides.
 */
class ChargeContextGuesser
{
    /**
     * Rayon de reconnaissance d'une borne deja utilisee. Assez large pour couvrir
     * un parking et l'imprecision GPS, assez court pour ne pas confondre deux
     * bornes d'une meme commune.
     */
    private const SESSION_RADIUS_METERS = 250;

    /**
     * Rayon plus permissif pour la position manuelle d'une localisation : elle
     * designe souvent la ville plutot que la borne exacte.
     */
    private const LOCATION_RADIUS_METERS = 400;

    /**
     * @return array{location_id: int|null, provider_id: int|null, power_rating_id: int|null, location_name: string|null, distance_m: int|null, source: string|null}
     */
    public function guess(?float $latitude, ?float $longitude): array
    {
        $empty = [
            'location_id' => null,
            'provider_id' => null,
            'power_rating_id' => null,
            'location_name' => null,
            'distance_m' => null,
            'source' => null,
        ];

        if ($latitude === null || $longitude === null) {
            return $empty;
        }

        return $this->fromPastSessions($latitude, $longitude)
            ?? $this->fromLocation($latitude, $longitude)
            ?? $empty;
    }

    /**
     * Recharge deja saisie la plus proche : elle porte le lieu, le fournisseur et
     * la puissance reellement utilises a cette borne.
     *
     * @return array<string, mixed>|null
     */
    private function fromPastSessions(float $latitude, float $longitude): ?array
    {
        $nearest = null;
        $nearestDistance = null;

        $sessions = ChargingSession::with('location')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('location_id')
            ->orderByDesc('session_date')
            ->get();

        foreach ($sessions as $session) {
            $distance = $this->distanceMeters($latitude, $longitude, (float) $session->latitude, (float) $session->longitude);

            // Strictement inferieur : a egalite de distance, la plus recente
            // gagne, l'ordre de tri s'en charge.
            if ($distance <= self::SESSION_RADIUS_METERS && ($nearestDistance === null || $distance < $nearestDistance)) {
                $nearest = $session;
                $nearestDistance = $distance;
            }
        }

        if ($nearest === null) {
            return null;
        }

        return [
            'location_id' => $nearest->location_id,
            'provider_id' => $nearest->provider_id,
            'power_rating_id' => $nearest->power_rating_id,
            'location_name' => $nearest->location?->name,
            'distance_m' => (int) round($nearestDistance),
            'source' => 'recharge',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromLocation(float $latitude, float $longitude): ?array
    {
        $nearest = null;
        $nearestDistance = null;

        foreach (Location::whereNotNull('latitude')->whereNotNull('longitude')->get() as $location) {
            $distance = $this->distanceMeters($latitude, $longitude, (float) $location->latitude, (float) $location->longitude);

            if ($distance <= self::LOCATION_RADIUS_METERS && ($nearestDistance === null || $distance < $nearestDistance)) {
                $nearest = $location;
                $nearestDistance = $distance;
            }
        }

        if ($nearest === null) {
            return null;
        }

        return array_merge([
            'location_id' => $nearest->id,
            'location_name' => $nearest->name,
            'distance_m' => (int) round($nearestDistance),
            'source' => 'localisation',
            'provider_id' => null,
            'power_rating_id' => null,
        ], $this->habitsAt($nearest->id));
    }

    /**
     * Couple fournisseur / puissance le plus souvent saisi a cet endroit, faute de
     * mieux : sans position sur les recharges passees, on ne peut pas distinguer
     * les bornes d'une meme localisation.
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
