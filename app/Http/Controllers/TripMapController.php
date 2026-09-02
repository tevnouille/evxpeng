<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripMapController extends Controller
{
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

        return view('trips.index', [
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'days' => $days,
            'date' => $date,
            'points' => $points,
            'distanceOdometer' => $this->odometerDistance($points),
            'distanceGps' => $this->gpsDistance($points),
            'mapPoints' => $points->map(fn ($row) => [
                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'time' => $row->recorded_at->format('H:i:s'),
                'soc' => $row->soc !== null ? (float) $row->soc : null,
                'speed' => $row->speed !== null ? (float) $row->speed : null,
                'odometer' => $row->odometer,
                'charging' => (bool) $row->is_charging,
            ])->values(),
        ]);
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
