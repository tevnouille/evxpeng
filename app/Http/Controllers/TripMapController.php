<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\ReverseGeocoder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripMapController extends Controller
{
    /**
     * Au-dela de cet ecart entre deux releves consecutifs, on considere que la
     * voiture a ete coupee (le boitier OBD ne remonte plus rien moteur
     * eteint) : c'est la limite entre deux deplacements distincts de la meme
     * journee.
     *
     * Verifie sur l'historique complet (2026-09-10, 4079 releves du seul
     * vehicule equipe) : les ecarts de plus de cinq minutes se repartissent en
     * deux groupes nettement separes, l'un sous 840 s (bref decrochage reseau
     * en roulant ou en stationnement, le boitier restant branche), l'autre a
     * partir de 1314 s (arret reel, moteur coupe). 900 s tombe dans le creux
     * entre les deux : ni assez court pour couper un trajet sur un long feu,
     * ni assez long pour fondre deux sorties separees dans le meme trajet.
     */
    private const TRIP_GAP_SECONDS = 900;

    public function __construct(private readonly ReverseGeocoder $geocoder)
    {
    }

    public function index(Request $request): View
    {
        $vehicles = Vehicle::whereNotNull('mqtt_client_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $vehicle = $vehicles->firstWhere('id', (int) $request->query('vehicule')) ?? $vehicles->first();

        // Jours disposant d'au moins une position, pour ne proposer que des dates
        // qui donneront quelque chose a voir.
        $days = $vehicle
            ? $vehicle->telemetries()
                ->whereNotNull('lat')
                ->selectRaw('DATE(recorded_at) as day, COUNT(*) as points')
                ->groupBy('day')
                ->orderByDesc('day')
                ->get()
            : collect();

        $date = $request->query('date') ?: $days->first()?->day;

        $points = ($vehicle && $date)
            ? $vehicle->telemetries()
                ->whereNotNull('lat')
                ->whereDate('recorded_at', $date)
                ->orderBy('recorded_at')
                ->get()
            : collect();

        // Une seule requete groupee vers la Base Adresse Nationale, et le
        // resultat est garde definitivement : la journee consultee une seconde
        // fois ne coute plus rien. Un echec laisse simplement la colonne vide.
        $this->geocoder->resolveMissing($points);
        $adresses = $this->geocoder->known($points);

        // Calcule une seule fois : la vue s'en sert pour colorer la carte, le
        // selecteur de deplacements et le filtre du tableau des releves.
        $tripIndexes = $this->tripIndexes($points);
        $trips = $this->trips($points, $tripIndexes);

        return view('trips.index', [
            'readings' => $points->values()->map(function ($row, $i) use ($adresses, $tripIndexes) {
                $place = $adresses[$this->geocoder->key((float) $row->lat, (float) $row->lon)] ?? null;

                return [
                    'at' => $row->recorded_at,
                    'lat' => (float) $row->lat,
                    'lon' => (float) $row->lon,
                    'label' => ($place && $place->label !== '') ? $place->label : null,
                    'distance_m' => $place?->distance_m,
                    'speed' => $row->speed !== null ? (float) $row->speed : null,
                    'charging' => (bool) $row->is_charging,
                    'trip' => $tripIndexes[$i] ?? 0,
                ];
            })->values(),
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'days' => $days,
            'date' => $date,
            'points' => $points,
            'distanceOdometer' => $this->odometerDistance($points),
            'distanceGps' => $this->gpsDistance($points),
            'trips' => $trips,
            'mapPoints' => $points->values()->map(fn ($row, $i) => [
                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'time' => $row->recorded_at->format('H:i:s'),
                'soc' => $row->soc !== null ? (float) $row->soc : null,
                'speed' => $row->speed !== null ? (float) $row->speed : null,
                'odometer' => $row->odometer,
                'charging' => (bool) $row->is_charging,
                'trip' => $tripIndexes[$i] ?? 0,
            ])->values(),
        ]);
    }

    /**
     * Indice de deplacement (0, 1, 2...) pour chaque releve, dans l'ordre de
     * $points, sur les ecarts de temps entre releves consecutifs.
     *
     * @return array<int, int>
     */
    private function tripIndexes($points): array
    {
        $indexes = [];
        $trip = 0;
        $previous = null;

        foreach ($points->values() as $i => $row) {
            if ($previous !== null && $previous->diffInSeconds($row->recorded_at) > self::TRIP_GAP_SECONDS) {
                $trip++;
            }

            $indexes[$i] = $trip;
            $previous = $row->recorded_at;
        }

        return $indexes;
    }

    /**
     * Resume par deplacement, pour le selecteur de la vue : plage horaire,
     * nombre de releves, distance parcourue (au compteur, quand disponible).
     *
     * @param  array<int, int>  $tripIndexes
     * @return \Illuminate\Support\Collection
     */
    private function trips($points, array $tripIndexes)
    {
        return $points->values()
            ->groupBy(fn ($row, $i) => $tripIndexes[$i] ?? 0)
            ->map(fn ($rows, $trip) => [
                'trip' => (int) $trip,
                'from' => $rows->first()->recorded_at,
                'to' => $rows->last()->recorded_at,
                'count' => $rows->count(),
                'distance' => $this->odometerDistance($rows),
            ])
            ->values();
    }

    /**
     * Distance reelle, lue au compteur. C'est la seule fiable : les positions ne
     * sont echantillonnees qu'a la minute au mieux.
     */
    private function odometerDistance($points): ?int
    {
        $withOdometer = $points->whereNotNull('odometer');

        if ($withOdometer->count() < 2) {
            return null;
        }

        return max(0, (int) $withOdometer->last()->odometer - (int) $withOdometer->first()->odometer);
    }

    /**
     * Somme des distances a vol d'oiseau entre releves successifs.
     *
     * A ne jamais prendre pour la distance parcourue : elle sous-estime des que
     * la voiture roule (la route n'est pas droite, les points sont espaces) mais
     * elle surestime a l'arret, ou le bruit GPS fait bouger des releves pourtant
     * immobiles. Observe des le premier jour de donnees : 2,3 km calcules pour
     * 2 km au compteur. Affichee a titre indicatif uniquement.
     */
    private function gpsDistance($points): ?float
    {
        if ($points->count() < 2) {
            return null;
        }

        $total = 0.0;
        $previous = null;

        foreach ($points as $point) {
            if ($previous !== null) {
                $total += $this->haversine(
                    (float) $previous->lat, (float) $previous->lon,
                    (float) $point->lat, (float) $point->lon
                );
            }

            $previous = $point;
        }

        return round($total, 1);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
