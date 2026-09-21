<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\DailyVehicleActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kilometrage cumule, par mois et par annee, sur toute la duree connue du
 * vehicule (odometre du boitier OBD).
 *
 * Distinct du tableau quotidien de « Ma voiture », qui ne montre qu'un mois a
 * la fois : celui-ci sert a voir la tendance dans la duree, pas le detail
 * d'une periode.
 */
class MileageController extends Controller
{
    public function __construct(private readonly DailyVehicleActivity $activity)
    {
    }

    public function index(Request $request): View
    {
        $vehicles = Vehicle::whereNotNull('mqtt_client_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $vehicle = $vehicles->firstWhere('id', (int) $request->query('vehicule')) ?? $vehicles->first();

        $byMonth = $vehicle ? $this->activity->perMonth($vehicle) : [];

        $byYear = [];
        foreach ($byMonth as $row) {
            $year = substr($row['month'], 0, 4);
            $byYear[$year] = ($byYear[$year] ?? 0) + $row['km'];
        }
        krsort($byYear);

        return view('my_vehicle.mileage', [
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'byMonth' => $byMonth,
            'byYear' => $byYear,
            'totalKm' => array_sum(array_column($byMonth, 'km')),
        ]);
    }
}
