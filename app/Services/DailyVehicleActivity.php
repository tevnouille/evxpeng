<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kilometres parcourus et energie rechargee, jour par jour.
 *
 * Deux sources distinctes, qui n'ont pas la meme fiabilite :
 *  - les kilometres viennent de l'odometre, present seulement quand la source
 *    est un dongle OBD (le cloud du constructeur seul ne le remonte pas) ;
 *  - l'energie vient des recharges reconstituees par {@see TelemetrySessionDetector},
 *    donc de l'ecart de niveau de charge : c'est l'energie *entree dans la
 *    batterie*, inferieure a celle facturee a la borne.
 *
 * Un jour sans aucun releve n'est pas un jour a zero kilometre : les deux cas
 * sont distingues explicitement (`has_data`), sans quoi une panne de collecte
 * se lirait comme une journee sans rouler.
 */
class DailyVehicleActivity
{
    /**
     * Ecart d'odometre au-dela duquel on ne croit plus a un trajet entre deux
     * releves : changement de source, ressaisie manuelle ou compteur remis a
     * zero. Large a dessein — une journee de route depasse rarement ce seuil.
     */
    private const MAX_JUMP_KM = 2000;

    /**
     * En deca de cette distance, rapporter l'energie rechargee aux kilometres
     * parcourus ne veut plus rien dire : une seule charge sur un mois a peine
     * entame donne des centaines de kWh/100 km. Le chiffre est alors tu.
     */
    private const MIN_KM_FOR_CONSUMPTION = 100;

    public function __construct(private readonly TelemetrySessionDetector $detector)
    {
    }

    /**
     * Mois pour lesquels il existe au moins un releve, du plus recent au plus ancien.
     *
     * @return array<int, string>  Au format `YYYY-MM`.
     */
    public function availableMonths(Vehicle $vehicle): array
    {
        $bounds = $vehicle->telemetries()
            ->selectRaw('MIN(recorded_at) AS first_at, MAX(recorded_at) AS last_at')
            ->first();

        if (! $bounds?->first_at) {
            return [];
        }

        $cursor = CarbonImmutable::parse($bounds->first_at)->startOfMonth();
        $last = CarbonImmutable::parse($bounds->last_at)->startOfMonth();

        $months = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return array_reverse($months);
    }

    /**
     * Kilometres parcourus par mois, sur toute la duree connue du vehicule.
     *
     * Meme regle que forMonth() (ecart negatif ou superieur a MAX_JUMP_KM
     * ignore, changement de source ou remise a zero), mais un seul passage sur
     * l'historique complet plutot qu'un mois a la fois : l'agregation ne porte
     * que sur l'odometre, pas les recharges, bien moins couteux.
     *
     * Chaque mois porte aussi son detail par jour (seuls les jours avec des
     * kilometres), dans l'ordre chronologique : calcule dans le meme passage,
     * un jour a cheval sur minuit etant compte au jour d'arrivee comme dans
     * forMonth().
     *
     * @return array<int, array{month: string, km: int, days: array<int, array{date: string, km: int}>}>  Le plus recent d'abord.
     */
    public function perMonth(Vehicle $vehicle): array
    {
        $rows = $vehicle->telemetries()
            ->whereNotNull('odometer')
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'odometer']);

        $parJour = [];
        $previousOdometer = null;

        foreach ($rows as $row) {
            $odometer = (int) $row->odometer;

            if ($previousOdometer !== null) {
                $delta = $odometer - $previousOdometer;

                if ($delta > 0 && $delta <= self::MAX_JUMP_KM) {
                    $key = $row->recorded_at->timezone(config('app.timezone'))->format('Y-m-d');
                    $parJour[$key] = ($parJour[$key] ?? 0) + $delta;
                }
            }

            $previousOdometer = $odometer;
        }

        $mois = [];

        foreach ($parJour as $jour => $km) {
            $cle = substr($jour, 0, 7);
            $mois[$cle] ??= ['month' => $cle, 'km' => 0, 'days' => []];
            $mois[$cle]['km'] += $km;
            $mois[$cle]['days'][] = ['date' => $jour, 'km' => $km];
        }

        krsort($mois);

        return array_values($mois);
    }

    /**
     * @return array{days: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function forMonth(Vehicle $vehicle, CarbonImmutable $month, ?float $netCapacityKwh): array
    {
        $start = $month->startOfMonth();
        $end = $start->endOfMonth();

        // Un releve anterieur au mois est necessaire : sans lui, la distance
        // parcourue le 1er serait perdue faute de point de comparaison.
        $previousRow = $vehicle->telemetries()
            ->where('recorded_at', '<', $start)
            ->orderByDesc('recorded_at')
            ->first();

        $rows = $vehicle->telemetries()
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get();

        $days = $this->emptyDays($start, $end);
        $this->addDistances($days, $rows, $previousRow);
        $this->addCharges($days, $rows, $previousRow, $start, $end, $netCapacityKwh);

        foreach ($rows as $row) {
            $key = $this->dayKey($row);

            if (isset($days[$key])) {
                $days[$key]['has_data'] = true;
            }
        }

        return [
            'days' => array_values($days),
            'totals' => $this->totals($days),
        ];
    }

    /**
     * Squelette du mois : un jour sans releve doit apparaitre comme tel, pas
     * disparaitre du tableau.
     *
     * @return array<string, array<string, mixed>>
     */
    private function emptyDays(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            // Les jours a venir n'ont rien a dire : on s'arrete a aujourd'hui.
            if ($day->greaterThan($today)) {
                break;
            }

            $days[$day->format('Y-m-d')] = [
                'date' => $day,
                'km' => 0,
                'kwh' => 0.0,
                'charges' => 0,
                'has_data' => false,
                'has_odometer' => false,
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, array<string, mixed>>  $days
     * @param  Collection<int, VehicleTelemetry>  $rows
     */
    private function addDistances(array &$days, Collection $rows, ?VehicleTelemetry $previousRow): void
    {
        $previousOdometer = $previousRow?->odometer !== null ? (int) $previousRow->odometer : null;

        foreach ($rows as $row) {
            if ($row->odometer === null) {
                continue;
            }

            $odometer = (int) $row->odometer;
            $key = $this->dayKey($row);

            if (isset($days[$key])) {
                $days[$key]['has_odometer'] = true;
            }

            if ($previousOdometer !== null) {
                $delta = $odometer - $previousOdometer;

                // Un odometre ne recule pas : un ecart negatif ou aberrant
                // signale un changement de source, pas un trajet.
                if ($delta > 0 && $delta <= self::MAX_JUMP_KM && isset($days[$key])) {
                    // Un trajet a cheval sur minuit est compte le jour de son
                    // point d'arrivee : c'est le seul rattachement possible avec
                    // des releves espaces.
                    $days[$key]['km'] += $delta;
                }
            }

            $previousOdometer = $odometer;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $days
     * @param  Collection<int, VehicleTelemetry>  $rows
     */
    private function addCharges(
        array &$days,
        Collection $rows,
        ?VehicleTelemetry $previousRow,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?float $netCapacityKwh
    ): void {
        if (! $netCapacityKwh) {
            return;
        }

        // Le releve precedent sert de niveau de depart a une charge commencee
        // juste avant le mois ; le detecteur attend une suite chronologique.
        $series = $previousRow ? collect([$previousRow])->concat($rows) : $rows;

        foreach ($this->detector->detect($series, $netCapacityKwh) as $session) {
            $startedAt = $session['started_at']->timezone(config('app.timezone'));

            // Une charge commencee le mois precedent appartient a ce mois-la.
            if ($startedAt->lessThan($start) || $startedAt->greaterThan($end)) {
                continue;
            }

            $key = $startedAt->format('Y-m-d');

            if (! isset($days[$key])) {
                continue;
            }

            $days[$key]['charges']++;
            $days[$key]['kwh'] += $session['kwh'] ?? 0.0;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function totals(array $days): array
    {
        $km = array_sum(array_column($days, 'km'));
        $kwh = round(array_sum(array_column($days, 'kwh')), 2);
        $withData = count(array_filter($days, fn ($day) => $day['has_data']));
        $withOdometer = count(array_filter($days, fn ($day) => $day['has_odometer']));

        return [
            'km' => $km,
            'kwh' => $kwh,
            'charges' => array_sum(array_column($days, 'charges')),
            'days_with_data' => $withData,
            'days_with_odometer' => $withOdometer,
            // Consommation apparente du mois. Elle melange l'energie rechargee
            // et les kilometres parcourus, qui ne couvrent pas exactement la
            // meme periode : indicative, pas comptable.
            'kwh_per_100km' => ($km >= self::MIN_KM_FOR_CONSUMPTION && $kwh > 0)
                ? round($kwh / $km * 100, 1)
                : null,
        ];
    }

    private function dayKey(VehicleTelemetry $row): string
    {
        return $row->recorded_at->timezone(config('app.timezone'))->format('Y-m-d');
    }
}
