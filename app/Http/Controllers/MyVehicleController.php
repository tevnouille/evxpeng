<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use App\Services\BatteryHealth;
use App\Services\ChargingCurveRepository;
use App\Services\MeasuredChargingSessions;
use App\Services\PendingTelemetryCharges;
use App\Services\DailyVehicleActivity;
use App\Services\TelemetrySessionDetector;
use App\Services\TelemetrySources;
use App\Services\VehicleState;
use App\Services\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyVehicleController extends Controller
{
    private const DEFAULT_DAYS = 14;

    /**
     * En dessous, rapporter le cout aux kilometres ne veut plus rien dire :
     * une seule recharge sur une fenetre a peine entamee donnerait un chiffre
     * enorme. Meme logique que MIN_KM_FOR_CONSUMPTION dans DailyVehicleActivity.
     */
    private const MIN_KM_FOR_COST = 50;

    /**
     * En dessous, une moyenne quotidienne se fait sur trop peu de jours pour
     * etre autre chose qu'un instantane : deux jours de vacances sans rouler
     * feraient croire que la voiture ne consomme plus rien.
     */
    private const MIN_COVERAGE_DAYS_FOR_ESTIMATE = 3;

    public function __construct(
        private readonly ChargingCurveRepository $curves,
        private readonly TelemetrySessionDetector $detector,
        private readonly DailyVehicleActivity $activity,
        private readonly VehicleState $state,
        private readonly TelemetrySources $sources,
        private readonly MeasuredChargingSessions $measured,
        private readonly BatteryHealth $battery,
        private readonly WeatherService $weather,
    ) {
    }

    public function index(Request $request): View
    {
        // Seuls les vehicules relies a ABRP ont quelque chose a montrer ici.
        $vehicles = Vehicle::whereNotNull('mqtt_client_id')
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

        if ($month !== null && $activity['days'] !== []) {
            $activity['days'] = $this->withWeather($activity['days'], $month, $telemetry);
        }

        $sessions = $vehicle
            ? $this->sessions($vehicle, $history, $netCapacity, $days, $request->boolean('toutes'))
            : ['visibles' => [], 'masquees' => 0];

        // Etat reconstruit : `is_charging` seul ne distingue pas le roulage du
        // stationnement, et `is_parked` n'est jamais renseigne.
        $state = $this->state->describe($telemetry, $history);

        // Signal avant-coureur de degradation batterie : le SoH ne bouge pas
        // assez vite pour se lire sur une courbe (App\Services\BatteryHealth).
        $healthTrend = $this->battery->dailyMedianGap($history);

        $costPerKm = $vehicle ? $this->costPerKm($vehicle, $history, $days) : ['km' => null, 'cost' => 0.0, 'per_km' => null];

        // Question posee dans le TODO avant de s'y lancer : utile seulement si
        // l'estimation reste grossiere et honnete sur ses limites — un usage
        // qui recharge tous les soirs a la maison n'a jamais vraiment besoin de
        // "planifier" une recharge, mais voir le rythme actuel reste un repere.
        $nextCharge = $this->nextChargeEstimate($history, $rangeKm);

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
            'state' => $state,
            // Juge sur la seule fraicheur des releves : aucun drapeau de source ne
            // dit si la donnee arrive encore.
            'link' => $this->state->link($telemetry, $history),
            // Cadence de rechargement de la page : suivre une charge se fait au
            // rythme ou la puissance evolue, une voiture a l'arret n'a rien a
            // raconter. La table vit dans VehicleState, aux cotes des etats.
            'refreshSeconds' => VehicleState::REFRESH_SECONDS[$state['state'] ?? VehicleState::PARKED]
                ?? VehicleState::REFRESH_SECONDS[VehicleState::PARKED],
            'curve' => $curve,
            'days' => $days,
            'soc' => $soc,
            'availableKwh' => $availableKwh,
            'netCapacity' => $netCapacity,
            'rangeKm' => $rangeKm,
            // Ce que chaque source remonte reellement : le cloud constructeur
            // et le dongle OBD ne fournissent pas les memes champs.
            'sources' => $this->sources->summarize($history),
            'sessions' => $sessions['visibles'],
            'hiddenSessions' => $sessions['masquees'],
            'showingAllSessions' => $request->boolean('toutes'),
            // Detections ecartees de la page Recharges : elles restent listees
            // ici, marquees, avec de quoi les remettre en proposition.
            'ignoredCharges' => $vehicle ? PendingTelemetryCharges::ignoredKeys([$vehicle->id]) : [],
            'chartLabels' => $history->map(fn ($row) => $row->recorded_at->timezone(config('app.timezone'))->format('d/m H:i'))->values(),
            'chartSoc' => $history->pluck('soc')->map(fn ($v) => $v === null ? null : (float) $v)->values(),
            'chartCharging' => $history->pluck('is_charging')->map(fn ($v) => (bool) $v)->values(),
            'pointCount' => $history->count(),
            'healthTrend' => $healthTrend,
            'healthLabels' => collect($healthTrend)->map(fn ($d) => CarbonImmutable::createFromFormat('Y-m-d', $d['date'])->format('d/m'))->values(),
            'healthGapMv' => collect($healthTrend)->pluck('median')->values(),
            'costPerKm' => $costPerKm,
            'nextCharge' => $nextCharge,
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
    private function sessions($vehicle, $history, ?float $netCapacity, int $days, bool $toutes): array
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

        // Meme seuil que la page Recharges : depuis que les releves arrivent au
        // quart de minute, le detecteur prend chaque scintillement de charge en
        // roulage pour une session. Rien n'est perdu, seulement replie.
        $visibles = $toutes
            ? $sessions
            : array_values(array_filter($sessions, PendingTelemetryCharges::isSignificant(...)));

        return ['visibles' => $visibles, 'masquees' => count($sessions) - count($visibles)];
    }

    /**
     * Cout reellement facture au kilometre, sur la meme fenetre glissante que
     * le reste de la page. Croise les recharges saisies (ChargingSession) et
     * la distance parcourue (odometre du boitier OBD).
     *
     * Ne compte que ce que les recharges ont coute : aucun abonnement
     * domicile/box n'existe dans le modele de donnees de l'application, et
     * rien d'autre ici ne raisonne avec — l'ajouter serait une fonctionnalite
     * a part, pas une hypothese a glisser dans ce calcul.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\VehicleTelemetry>  $history
     * @return array{km: int|null, cost: float, per_km: float|null}
     */
    private function costPerKm(Vehicle $vehicle, $history, int $days): array
    {
        $withOdometer = $history->whereNotNull('odometer')->values();
        $km = null;
        $periodStart = null;

        if ($withOdometer->count() >= 2) {
            $delta = (int) $withOdometer->last()->odometer - (int) $withOdometer->first()->odometer;

            // Compteur remis a zero, ou changement de source : un ecart
            // negatif n'est pas une distance.
            $km = $delta >= 0 ? $delta : null;
            $periodStart = $withOdometer->first()->recorded_at;
        }

        // Bornees a la periode reellement couverte par l'odometre, pas aux
        // $days demandes : au-dela (avant l'installation du boitier, ou un
        // simple trou de telemetrie), le cout gonflerait sans que la distance
        // suive, faussant le ratio plutot que de le laisser vide.
        $cost = ($periodStart !== null)
            ? (float) ChargingSession::where('vehicle_id', $vehicle->id)
                ->where('session_date', '>=', $periodStart)
                ->sum('total_cost')
            : 0.0;

        return [
            'km' => $km,
            'cost' => round($cost, 2),
            'per_km' => ($km !== null && $km >= self::MIN_KM_FOR_COST) ? round($cost / $km, 3) : null,
        ];
    }

    /**
     * Ajoute la temperature moyenne du jour a chaque ligne du tableau
     * quotidien. Le boitier ne la remonte jamais — verifie sur l'historique
     * complet, la colonne `ext_temp` reste vide — d'ou App\Services\WeatherService.
     *
     * Une seule position pour tout le mois affiche (le dernier releve connu) :
     * indicatif, comme les autres approximations geographiques de
     * l'application.
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array<int, array<string, mixed>>
     */
    private function withWeather(array $days, CarbonImmutable $month, ?VehicleTelemetry $telemetry): array
    {
        if ($telemetry === null || $telemetry->lat === null || $telemetry->lon === null) {
            return $days;
        }

        $weather = $this->weather->forRange(
            $month->startOfMonth(),
            $month->endOfMonth(),
            (float) $telemetry->lat,
            (float) $telemetry->lon
        );

        foreach ($days as &$day) {
            $day['temp_mean'] = $weather[$day['date']->format('Y-m-d')]['mean'] ?? null;
        }
        unset($day);

        return $days;
    }

    /**
     * Estimation grossiere du delai avant qu'une recharge devienne
     * necessaire, a partir du rythme de roulage recent (odometre) et de
     * l'autonomie actuelle.
     *
     * Volontairement simple : une moyenne lineaire sur la fenetre couverte,
     * rien qui modelise les jours de la semaine ou les trajets a venir. Une
     * voiture rechargee tous les soirs a la maison n'a jamais vraiment besoin
     * de "planifier" une recharge ; ce chiffre reste un repere, pas une alerte.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\VehicleTelemetry>  $history
     * @return array{km_per_day: float, days_remaining: int}|null
     */
    private function nextChargeEstimate($history, ?int $rangeKm): ?array
    {
        if ($rangeKm === null) {
            return null;
        }

        $withOdometer = $history->whereNotNull('odometer')->values();

        if ($withOdometer->count() < 2) {
            return null;
        }

        $delta = (int) $withOdometer->last()->odometer - (int) $withOdometer->first()->odometer;
        // diffInDays() rend un flottant depuis Carbon 3 (deja rencontre sur
        // diffInMinutes() pour la comparaison de trajets) : arrondi a l'entier
        // inferieur, la fraction de journee ne dit rien de plus ici.
        $coverageDays = (int) floor($withOdometer->first()->recorded_at->diffInDays($withOdometer->last()->recorded_at));

        if ($delta <= 0 || $coverageDays < self::MIN_COVERAGE_DAYS_FOR_ESTIMATE) {
            return null;
        }

        $kmPerDay = $delta / $coverageDays;

        if ($kmPerDay <= 0) {
            return null;
        }

        return [
            'km_per_day' => round($kmPerDay, 1),
            'days_remaining' => (int) floor($rangeKm / $kmPerDay),
            'coverage_days' => $coverageDays,
        ];
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
