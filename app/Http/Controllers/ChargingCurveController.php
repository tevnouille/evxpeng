<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use App\Services\TelemetrySessionDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingCurveController extends Controller
{
    /** Puissances de borne proposees, en kW. */
    public const CHARGER_POWERS = [7.4, 11.0, 50.0, 150.0, 300.0];

    public function __construct(private readonly ChargingCurveRepository $curves)
    {
    }

    public function index(Request $request): View
    {
        // Seuls les vehicules auxquels une courbe a ete associee dans
        // /admin/vehicules sont proposes.
        $vehicles = Vehicle::whereNotNull('charging_curve')
            ->with('latestTelemetry')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->filter(fn ($vehicle) => $this->curves->find($vehicle->charging_curve) !== null)
            ->values();

        $requested = $request->query('vehicule');

        // A defaut de choix explicite, le vehicule par defaut (orderByDesc ci-dessus
        // le place en tete).
        $vehicle = $vehicles->firstWhere('id', (int) $requested) ?? $vehicles->first();

        $curve = $vehicle ? $this->curves->find($vehicle->charging_curve) : null;

        // Sans choix explicite, on deduit la borne de la puissance reellement
        // mesuree pendant la charge. `has` et non `filled` : choisir "sans
        // limite" envoie borne= vide, et c'est un choix qu'il faut respecter.
        $capAuto = null;

        if (! $request->has('borne')) {
            $capAuto = $this->capFromMeasuredPower($vehicle?->latestTelemetry);
        }

        $cap = $capAuto ?? $this->requestedCap($request);

        if ($curve) {
            $curve = $this->withDerivedColumns($curve, $cap);
        }

        // Niveau de charge remonte par ABRP, arrondi au point de courbe le plus
        // proche : la courbe est echantillonnee au pourcent entier.
        $telemetry = $vehicle?->latestTelemetry;
        $currentSoc = null;
        $currentPoint = null;

        if ($curve && $telemetry && $telemetry->soc !== null) {
            $currentSoc = (int) round((float) $telemetry->soc);
            $currentPoint = collect($curve['points'])->firstWhere('soc', $currentSoc);
        }

        return view('charging_curves.index', [
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'curve' => $curve,
            'telemetry' => $telemetry,
            'currentSoc' => $currentSoc,
            'currentPoint' => $currentPoint,
            'chargerPowers' => self::CHARGER_POWERS,
            'cap' => $cap,
            'capAuto' => $capAuto,
        ]);
    }

    /**
     * Etat courant du vehicule, pour le rafraichissement du bloc "Niveau actuel"
     * sans rechargement de page.
     *
     * On relit la base, pas ABRP : c'est la commande planifiee qui interroge
     * l'API. Interroger ABRP a chaque appel de cette route ferait dependre le
     * nombre de requetes du nombre d'onglets ouverts.
     */
    public function state(Request $request, Vehicle $vehicle, TelemetrySessionDetector $detector): JsonResponse
    {
        $telemetry = $vehicle->latestTelemetry;

        if (! $telemetry) {
            return response()->json(['available' => false]);
        }

        $curve = $vehicle->charging_curve ? $this->curves->find($vehicle->charging_curve) : null;

        if ($curve) {
            $curve = $this->withDerivedColumns($curve, $this->requestedCap($request));
        }

        $soc = $telemetry->soc !== null ? (float) $telemetry->soc : null;
        $point = ($curve && $soc !== null)
            ? collect($curve['points'])->firstWhere('soc', (int) round($soc))
            : null;

        $session = null;

        if ($telemetry->is_charging) {
            $rows = $vehicle->telemetries()
                ->where('recorded_at', '>=', now()->subDay())
                ->orderBy('recorded_at')
                ->get();

            $session = collect($detector->detect($rows, $curve['battery_net_kwh'] ?? null))
                ->firstWhere('in_progress', true);
        }

        $power = $telemetry->power_kw !== null ? (float) $telemetry->power_kw : null;

        // Estimation fondee sur la puissance reellement mesuree, et non sur la
        // courbe : quand la voiture charge en alternatif, la courbe (etablie en
        // continu) surestime largement. C'est cette valeur qui est comparable a
        // l'estimation affichee par la voiture.
        $liveMinutes = function (int $target) use ($soc, $curve, $power): ?int {
            if ($soc === null || $power === null || $power >= 0 || $curve === null || $soc >= $target) {
                return null;
            }

            $energy = ($target - $soc) / 100 * (float) $curve['battery_net_kwh'];

            return (int) round($energy / abs($power) * 60);
        };

        return response()->json([
            'available' => true,
            'live_to_80' => $liveMinutes(80),
            'live_to_90' => $liveMinutes(90),
            'live_to_100' => $liveMinutes(100),
            'soc' => $soc,
            'is_charging' => (bool) $telemetry->is_charging,
            // Convention ABRP : negatif = energie entrante. On expose la valeur
            // absolue et le sens separement, l'affichage n'a pas a le deviner.
            'power_kw' => $power !== null ? round(abs($power), 1) : null,
            'power_incoming' => $power !== null ? $power < 0 : null,
            'batt_temp' => $telemetry->batt_temp !== null ? (float) $telemetry->batt_temp : null,
            'odometer' => $telemetry->odometer,
            'soh' => $telemetry->soh !== null ? (float) $telemetry->soh : null,
            'available_kwh' => $point['kwh'] ?? null,
            // Position du marqueur sur le graphique de puissance : abscisse (le
            // SoC arrondi au point de courbe) et ordonnee.
            'soc_rounded' => $soc === null ? null : (int) round($soc),
            'curve_kw' => $point['kw_effective'] ?? null,
            'to_80' => $point['to_80'] ?? null,
            'to_90' => $point['to_90'] ?? null,
            'to_100' => $point['to_100'] ?? null,
            'session_kwh' => $session['kwh'] ?? null,
            'session_soc_start' => $session['soc_start'] ?? null,
            'session_started_at' => isset($session['started_at'])
                ? $session['started_at']->timezone(config('app.timezone'))->format('d/m/Y H:i')
                : null,
            'session_minutes' => $session['duration_minutes'] ?? null,
            'recorded_at' => $telemetry->recorded_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
            'recorded_at_human' => $telemetry->recorded_at->diffForHumans(),
        ]);
    }

    /**
     * Deduit la puissance de borne a partir de la puissance mesuree en charge.
     *
     * ABRP ne dit pas de quelle borne il s'agit : on ne connait que ce que la
     * voiture absorbe. On retient donc la plus petite puissance proposee qui
     * couvre la mesure — 8,5 kW mesures donnent 11 kW. Une borne 22 kW bridee
     * par le chargeur embarque sera vue comme une 11 kW, ce qui est justement la
     * puissance utile ici.
     */
    private function capFromMeasuredPower(?\App\Models\VehicleTelemetry $telemetry): ?float
    {
        if (! $telemetry || ! $telemetry->is_charging || $telemetry->power_kw === null) {
            return null;
        }

        $measured = abs((float) $telemetry->power_kw);

        if ($measured <= 0) {
            return null;
        }

        foreach (self::CHARGER_POWERS as $power) {
            if ($power >= $measured) {
                return $power;
            }
        }

        return (float) max(self::CHARGER_POWERS);
    }

    /**
     * Puissance maximale de la borne choisie, ou null pour la courbe brute.
     */
    private function requestedCap(Request $request): ?float
    {
        $value = $request->query('borne');

        if ($value === null || $value === '') {
            return null;
        }

        $value = (float) str_replace(',', '.', (string) $value);

        return in_array($value, self::CHARGER_POWERS, true) ? $value : null;
    }

    /**
     * Ajoute a chaque point la puissance reellement delivree par la borne, les
     * temps recalcules en consequence, l'energie presente dans la batterie et le
     * temps restant pour atteindre 80 / 90 / 100 %.
     *
     * Le bridage n'ecrase pas les mesures d'evkx : a energie egale, une puissance
     * divisee par deux double la duree du segment. On etire donc chaque intervalle
     * du rapport entre sa puissance d'origine et la puissance bridee. Sans borne
     * selectionnee, le facteur vaut 1 et on retrouve exactement les durees source.
     */
    private function withDerivedColumns(array $curve, ?float $cap = null): array
    {
        $points = $curve['points'];
        $targets = [80, 90, 100];

        $cumulative = 0.0;
        $previous = null;

        foreach ($points as $index => $point) {
            $kw = (float) $point['kw'];
            $points[$index]['kw_effective'] = round($cap !== null ? min($cap, $kw) : $kw, 1);

            if ($previous !== null) {
                $elapsed = $this->toSeconds($point['time']) - $this->toSeconds($previous['time']);
                // La puissance representative du segment est la moyenne de ses
                // bornes : c'est elle qu'encode implicitement la duree relevee.
                $average = ((float) $previous['kw'] + $kw) / 2;
                $limited = $cap !== null ? min($cap, $average) : $average;

                $cumulative += $limited > 0 ? $elapsed * ($average / $limited) : 0;
            }

            $points[$index]['seconds'] = (int) round($cumulative);
            $points[$index]['time'] = $this->formatDuration((int) round($cumulative));

            // Capacite nette non calculee ici : elle serait identique a la colonne
            // "Energie chargee" de la courbe (evkx compte l'energie sur la capacite utile).
            $points[$index]['battery_gross_kwh'] = round($point['soc'] * $curve['battery_kwh'] / 100, 1);

            $previous = $point;
        }

        $secondsBySoc = array_column($points, 'seconds', 'soc');
        $kwhBySoc = array_column($points, 'kwh', 'soc');

        foreach ($points as $index => $point) {
            foreach ($targets as $target) {
                // Une cible deja atteinte (ou absente de la courbe) n'a pas de temps restant.
                $reached = $point['soc'] >= $target || ! isset($secondsBySoc[$target]);
                $remaining = $reached ? null : $secondsBySoc[$target] - $secondsBySoc[$point['soc']];

                $points[$index]['to_'.$target] = $reached ? null : $this->formatDuration($remaining);
                // Version numerique (minutes) pour le graphique.
                $points[$index]['to_'.$target.'_minutes'] = $reached ? null : round($remaining / 60, 2);
            }
        }

        $curve['points'] = $points;
        $curve['charger_kw'] = $cap;

        // Les indicateurs de tete doivent suivre le bridage, sans quoi la page
        // afficherait 300 kW et 21 minutes au-dessus d'un tableau qui dit l'inverse.
        if ($cap !== null) {
            $curve['max_power_kw'] = round(min($cap, (float) $curve['max_power_kw']), 1);
        }

        $curve['time_10_80'] = $this->spanLabel($secondsBySoc, 10, 80);
        $curve['avg_10_80_kw'] = $this->averagePower($secondsBySoc, $kwhBySoc, 10, 80);
        $curve['time_0_100'] = $this->spanLabel($secondsBySoc, 0, 100);
        $curve['avg_0_100_kw'] = $this->averagePower($secondsBySoc, $kwhBySoc, 0, 100);

        return $curve;
    }

    /**
     * @param  array<int, int>  $secondsBySoc
     */
    private function spanLabel(array $secondsBySoc, int $from, int $to): string
    {
        if (! isset($secondsBySoc[$from], $secondsBySoc[$to])) {
            return '—';
        }

        $seconds = $secondsBySoc[$to] - $secondsBySoc[$from];
        $hours = intdiv($seconds, 3600);
        $label = sprintf('%d m %d s', intdiv($seconds % 3600, 60), $seconds % 60);

        return $hours > 0 ? sprintf('%d h %s', $hours, $label) : $label;
    }

    /**
     * @param  array<int, int>  $secondsBySoc
     * @param  array<int, float>  $kwhBySoc
     */
    private function averagePower(array $secondsBySoc, array $kwhBySoc, int $from, int $to): ?float
    {
        if (! isset($secondsBySoc[$from], $secondsBySoc[$to], $kwhBySoc[$from], $kwhBySoc[$to])) {
            return null;
        }

        $seconds = $secondsBySoc[$to] - $secondsBySoc[$from];

        if ($seconds <= 0) {
            return null;
        }

        return round(((float) $kwhBySoc[$to] - (float) $kwhBySoc[$from]) / ($seconds / 3600), 1);
    }

    private function toSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(array_map('intval', explode(':', $time)), 3, 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
