<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use App\Services\TelemetrySessionDetector;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyVehicleController extends Controller
{
    private const DEFAULT_DAYS = 14;

    public function __construct(
        private readonly ChargingCurveRepository $curves,
        private readonly TelemetrySessionDetector $detector,
    ) {
    }

    public function index(Request $request): View
    {
        // Seuls les vehicules relies a ABRP ont quelque chose a montrer ici.
        $vehicles = Vehicle::whereNotNull('abrp_token')
            ->with('latestTelemetry')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $vehicle = $vehicles->firstWhere('id', (int) $request->query('vehicule')) ?? $vehicles->first();

        $days = max(1, min(90, (int) $request->query('jours', self::DEFAULT_DAYS)));

        $history = $vehicle
            ? $vehicle->telemetries()
                ->where('recorded_at', '>=', now()->subDays($days))
                ->orderBy('recorded_at')
                ->get()
            : collect();

        $telemetry = $vehicle?->latestTelemetry;
        $curve = $vehicle?->charging_curve ? $this->curves->find($vehicle->charging_curve) : null;
        $netCapacity = $curve['battery_net_kwh'] ?? null;

        $soc = $telemetry?->soc !== null ? (float) $telemetry->soc : null;
        $availableKwh = ($soc !== null && $netCapacity) ? round($soc / 100 * $netCapacity, 1) : null;

        // Autonomie estimee a partir de la consommation saisie sur la fiche du
        // vehicule : aucune valeur par defaut, comme ailleurs dans l'application.
        $consumption = $vehicle?->kwh_per_100km ? (float) $vehicle->kwh_per_100km : null;
        $rangeKm = ($availableKwh !== null && $consumption) ? (int) round($availableKwh / $consumption * 100) : null;

        // Champs presents dans la reponse ABRP mais sans colonne dediee : on les
        // lit dans la charge brute, ce qui evite une migration a chaque champ que
        // le constructeur ou le dongle se met a remonter.
        $raw = $telemetry?->raw ?? [];
        $rawTelemetry = $raw['telemetry'] ?? [];

        return view('my_vehicle.index', [
            'heading' => $rawTelemetry['heading'] ?? null,
            'headingLabel' => $this->cardinal($rawTelemetry['heading'] ?? null),
            'typecode' => $raw['typecode'] ?? null,
            'isConnected' => $raw['is_connected'] ?? null,
            'rawTelemetry' => $rawTelemetry,
            'rawEnvelope' => array_diff_key($raw, ['telemetry' => null]),
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'telemetry' => $telemetry,
            'curve' => $curve,
            'days' => $days,
            'soc' => $soc,
            'availableKwh' => $availableKwh,
            'netCapacity' => $netCapacity,
            'rangeKm' => $rangeKm,
            'sessions' => $this->detector->detect($history, $netCapacity),
            'chartLabels' => $history->map(fn ($row) => $row->recorded_at->timezone(config('app.timezone'))->format('d/m H:i'))->values(),
            'chartSoc' => $history->pluck('soc')->map(fn ($v) => $v === null ? null : (float) $v)->values(),
            'chartCharging' => $history->pluck('is_charging')->map(fn ($v) => (bool) $v)->values(),
            'pointCount' => $history->count(),
        ]);
    }

    /**
     * Cap en degres vers un point cardinal sur 16 secteurs.
     */
    private function cardinal(?float $heading): ?string
    {
        if ($heading === null) {
            return null;
        }

        $points = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSO', 'SO', 'OSO', 'O', 'ONO', 'NO', 'NNO'];

        return $points[(int) round(((float) $heading % 360) / 22.5) % 16];
    }
}
