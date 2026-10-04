<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\DailyVehicleActivity;
use App\Services\ObdReadings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Releves bruts du boitier OBD, jour par jour.
 *
 * La page « Ma voiture » montre l'etat courant et les recharges ; celle-ci
 * ouvre la boite : chaque capteur que le boitier remonte, sur la journee
 * choisie. Le calendrier sert de point d'entree parce que la donnee est
 * discontinue — le boitier n'emet que telephone present dans la voiture, et
 * une case vide est une information en soi.
 */
class ObdStatsController extends Controller
{
    public function __construct(
        private readonly ObdReadings $readings,
        private readonly DailyVehicleActivity $activity,
    ) {
    }

    public function index(Request $request): View
    {
        $vehicles = Vehicle::whereNotNull('mqtt_client_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $vehicle = $vehicles->firstWhere('id', (int) $request->query('vehicule')) ?? $vehicles->first();

        $months = $vehicle ? $this->activity->availableMonths($vehicle) : [];
        $month = $this->resolveMonth($request->query('mois'), $months);
        $days = ($vehicle && $month) ? $this->readings->daysInMonth($vehicle, $month) : [];

        $day = $this->resolveDay($request->query('jour'), $days, $month);
        $readings = ($vehicle && $day) ? $this->readings->forDay($vehicle, $day) : null;

        return view('my_vehicle.obd_stats', [
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'months' => $months,
            'month' => $month,
            'days' => $days,
            'day' => $day,
            'readings' => $readings,
            // Le calendrier commence un lundi : la grille est fixe, les cases
            // d'avant le 1er sont vides plutot que reportees du mois precedent.
            'leading' => $month ? ($month->startOfMonth()->dayOfWeekIso - 1) : 0,
        ]);
    }

    /**
     * @param  array<int, string>  $months
     */
    private function resolveMonth(?string $requested, array $months): ?CarbonImmutable
    {
        if ($months === []) {
            return null;
        }

        $chosen = in_array($requested, $months, true) ? $requested : $months[0];

        return CarbonImmutable::createFromFormat('Y-m-d', $chosen.'-01', config('app.timezone'))->startOfMonth();
    }

    /**
     * Jour demande, ramene a un jour qui porte reellement des releves.
     *
     * A defaut, le plus recent du mois : ouvrir la page sur une journee vide
     * donnerait a croire que le boitier n'a rien remonte.
     *
     * @param  array<string, int>  $days
     */
    private function resolveDay(?string $requested, array $days, ?CarbonImmutable $month): ?CarbonImmutable
    {
        if ($days === [] || $month === null) {
            return null;
        }

        $chosen = isset($days[$requested]) ? $requested : array_key_last($days);

        return CarbonImmutable::createFromFormat('Y-m-d', $chosen, config('app.timezone'))->startOfDay();
    }
}
