<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use App\Services\MeasuredChargingSessions;
use App\Services\PendingTelemetryCharges;
use App\Services\DailyVehicleActivity;
use App\Services\TelemetrySessionDetector;
use App\Services\TelemetrySources;
use App\Services\VehicleState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyVehicleController extends Controller
{
    private const DEFAULT_DAYS = 14;

    public function __construct(
        private readonly ChargingCurveRepository $curves,
        private readonly TelemetrySessionDetector $detector,
        private readonly DailyVehicleActivity $activity,
        private readonly VehicleState $state,
        private readonly TelemetrySources $sources,
        private readonly MeasuredChargingSessions $measured,
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
        $curve = $this->curves->forVehicle($vehicle);
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

        // Le tableau quotidien porte son propre filtre : il couvre un mois
        // entier, la ou la courbe raisonne en nombre de jours glissants.
        $months = $vehicle ? $this->activity->availableMonths($vehicle) : [];
        $month = $this->resolveMonth($request->query('mois'), $months);
        $activity = ($vehicle && $month)
            ? $this->activity->forMonth($vehicle, $month, $netCapacity)
            : ['days' => [], 'totals' => []];

        return view('my_vehicle.index', [
            'months' => $months,
            'month' => $month,
            'activityDays' => $activity['days'],
            'activityTotals' => $activity['totals'],
            'heading' => $rawTelemetry['heading'] ?? null,
            'headingLabel' => $this->cardinal($rawTelemetry['heading'] ?? null),
            'typecode' => $raw['typecode'] ?? null,
            'isConnected' => $raw['is_connected'] ?? null,
            'rawTelemetry' => $rawTelemetry,
            'rawEnvelope' => array_diff_key($raw, ['telemetry' => null]),
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'telemetry' => $telemetry,
            // Etat reconstruit : `is_charging` seul ne distingue pas le roulage
            // du stationnement, et `is_parked` n'est jamais renseigne.
            'state' => $this->state->describe($telemetry, $history),
            // Fraicheur des releves plutot que le drapeau d'ABRP : celui-ci
            // reste a « connectée » dongle debranche.
            'link' => $this->state->link($telemetry, $history, isset($raw['is_connected']) ? (bool) $raw['is_connected'] : null),
            'curve' => $curve,
            'days' => $days,
            'soc' => $soc,
            'availableKwh' => $availableKwh,
            'netCapacity' => $netCapacity,
            'rangeKm' => $rangeKm,
            // Ce que chaque source remonte reellement : le cloud constructeur
            // et le dongle OBD ne fournissent pas les memes champs.
            'sources' => $this->sources->summarize($history),
            'sessions' => $vehicle ? $this->sessions($vehicle, $history, $netCapacity, $days) : [],
            // Detections ecartees de la page Recharges : elles restent listees
            // ici, marquees, avec de quoi les remettre en proposition.
            'ignoredCharges' => $vehicle ? PendingTelemetryCharges::ignoredKeys([$vehicle->id]) : [],
            'chartLabels' => $history->map(fn ($row) => $row->recorded_at->timezone(config('app.timezone'))->format('d/m H:i'))->values(),
            'chartSoc' => $history->pluck('soc')->map(fn ($v) => $v === null ? null : (float) $v)->values(),
            'chartCharging' => $history->pluck('is_charging')->map(fn ($v) => (bool) $v)->values(),
            'pointCount' => $history->count(),
        ]);
    }

    /**
     * Recharges mesurees par le boitier et recharges reconstituees, en une
     * seule liste antichronologique.
     *
     * Une meme recharge peut etre vue des deux cotes : la mesuree fait foi, son
     * energie venant du compteur du BMS et non d'un produit SoC x capacite.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\VehicleTelemetry>  $history
     * @return array<int, array<string, mixed>>
     */
    private function sessions($vehicle, $history, ?float $netCapacity, int $days): array
    {
        $measured = $this->measured->forVehicle($vehicle, $days);

        // Pas de reconnaissance de lieu ici : la page ne l'affiche pas, et
        // chaque appel interroge la base des bornes.
        $sessions = $measured->map(fn ($row) => $this->measured->toArray($row, $vehicle, false))->all();

        foreach ($this->detector->detect($history, $netCapacity) as $session) {
            if ($this->measured->overlaps($measured, $session)) {
                continue;
            }

            $sessions[] = $this->measured->normalise($session);
        }

        usort($sessions, fn ($a, $b) => $b['started_at'] <=> $a['started_at']);

        return $sessions;
    }

    /**
     * Mois demande, ramene a un mois qui existe reellement.
     *
     * Le mois par defaut est le plus recent qui porte des releves, et non le
     * mois courant : apres une interruption de collecte, ouvrir la page sur un
     * tableau vide donnerait a croire que la voiture n'a pas roule.
     *
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
