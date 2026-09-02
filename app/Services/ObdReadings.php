<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use Carbon\CarbonImmutable;

/**
 * Releves du boitier OBD, indicateur par indicateur.
 *
 * Rien n'est ecrit en dur : la liste des indicateurs se deduit des cles
 * reellement presentes dans `raw['telemetry']`, comme le bloc « Sources de
 * données ». Un capteur que le boitier se mettrait a remonter apparait donc
 * seul, et l'inverse est vrai — la table garde des annees de releves dont
 * certains champs n'existent plus.
 *
 * Deux natures d'indicateur, deux presentations : ce qui se mesure donne une
 * courbe, ce qui se lit — un etat, un libelle — donne une liste de
 * changements. Tracer un booleen aurait fait une courbe carree illisible ;
 * lister 3 000 fois « Not Charging » n'aurait rien appris.
 */
class ObdReadings
{
    /**
     * Cles decrivant le releve lui-meme, pas la voiture.
     */
    private const METADATA = ['timestamp', 'localTime', 'lowPriorityLastUpdated'];

    /**
     * Nombre de points au-dela duquel une courbe est echantillonnee.
     *
     * A quinze secondes, une journee de roulage depasse les 2 000 points par
     * indicateur : le navigateur en tracerait cinquante courbes. On garde la
     * forme, pas chaque point.
     */
    private const MAX_POINTS = 600;

    /**
     * Intitules et unites des indicateurs connus. Un code absent d'ici
     * s'affiche tel quel : mieux vaut un code brut qu'un intitule invente.
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const KNOWN = [
        'stateOfCharge' => ['Niveau de charge', '%'],
        'VCU_SOC' => ['Niveau de charge (calculateur)', '%'],
        'stateOfHealth' => ['Santé de la batterie', '%'],
        'BMS_111A' => ['Santé de la batterie (BMS)', '%'],
        'batteryCapacity' => ['Capacité utile', 'kWh'],
        'batteryVoltage' => ['Tension batterie', 'V'],
        'BMS_HV_V' => ['Tension batterie (BMS)', 'V'],
        'DC_CHG_V' => ['Tension de charge', 'V'],
        'HV_C_V_MAX' => ['Tension de cellule, maximum', 'V'],
        'HV_C_V_MIN' => ['Tension de cellule, minimum', 'V'],
        'batteryCurrent' => ['Courant batterie', 'A'],
        'DC_CHG_A' => ['Courant de charge', 'A'],
        'power' => ['Puissance', 'kW'],
        'batteryTemperature' => ['Température batterie', '°C'],
        'HV_T_MIN' => ['Température batterie, minimum', '°C'],
        'COOLANT_T' => ['Température liquide de refroidissement', '°C'],
        'MOTOR_T' => ['Température moteur', '°C'],
        'FAST_CHG_T1' => ['Température prise rapide 1', '°C'],
        'FAST_CHG_T2' => ['Température prise rapide 2', '°C'],
        'SLOW_CHG_T1' => ['Température prise lente 1', '°C'],
        'SLOW_CHG_T2' => ['Température prise lente 2', '°C'],
        'SLOW_CHG_T3' => ['Température prise lente 3', '°C'],
        'AUX_V' => ['Batterie 12 V', 'V'],
        'speed' => ['Vitesse', 'km/h'],
        'gpsSpeed' => ['Vitesse GPS', 'km/h'],
        'odometer' => ['Compteur kilométrique', 'km'],
        'CLTC_RANGE' => ['Autonomie annoncée', 'km'],
        'altitude' => ['Altitude', 'm'],
        'heading' => ['Cap', '°'],
        'latitude' => ['Latitude', null],
        'longitude' => ['Longitude', null],
        'ACCEL_PEDAL' => ['Pédale d\'accélérateur', '%'],
        'BRAKE_PRESSURE' => ['Pression de freinage', 'bar'],
        'FRONT_MOTOR_RPM' => ['Régime moteur avant', 'tr/min'],
        'REAR_MOTOR_RPM' => ['Régime moteur arrière', 'tr/min'],
        'FRONT_MOTOR_TORQUE_REQ' => ['Couple demandé, avant', 'Nm'],
        'REAR_MOTOR_TORQUE_REQ' => ['Couple demandé, arrière', 'Nm'],
        'cumulativeCharge' => ['Énergie chargée depuis l\'origine', 'Ah'],
        'cumulativeDischarge' => ['Énergie déchargée depuis l\'origine', 'Ah'],
        'CHG_LIMIT' => ['Limite de charge', null],
        'BMS_CHG_STATUS' => ['État de charge (BMS)', null],
        'CHARGING_HVIL' => ['Verrouillage haute tension', null],
        'isCharging' => ['En charge', null],
        'chargingStatus' => ['Code état de charge', null],
        'chargingStatusDescription' => ['État de charge', null],
        'dataSource' => ['Source', null],

        // Champs des releves historiques, collectes via A Better Routeplanner :
        // ils nomment autrement les memes grandeurs. On ne les fusionne pas
        // avec les champs du boitier — ce serait affirmer une equivalence que
        // rien ne garantit — mais ils meritent un intitule lisible.
        'soc' => ['Niveau de charge (historique)', '%'],
        'is_charging' => ['En charge (historique)', null],
        'batt_temp' => ['Température batterie (historique)', '°C'],
        'ext_temp' => ['Température extérieure (historique)', '°C'],
        'soh' => ['Santé de la batterie (historique)', '%'],
        'lat' => ['Latitude (historique)', null],
        'lon' => ['Longitude (historique)', null],
        'elevation' => ['Altitude (historique)', 'm'],
        'is_dcfc' => ['Charge rapide (historique)', null],
        'is_parked' => ['Stationné (historique)', null],
        'calib_ref_cons' => ['Consommation de référence (historique)', null],
    ];

    /**
     * Nombre de releves par jour sur le mois, pour colorer le calendrier.
     *
     * @return array<string, int>  Indexe par 'Y-m-d'.
     */
    public function daysInMonth(Vehicle $vehicle, CarbonImmutable $month): array
    {
        return VehicleTelemetry::where('vehicle_id', $vehicle->id)
            ->whereBetween('recorded_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->selectRaw('DATE(recorded_at) AS jour, COUNT(*) AS n')
            ->groupBy('jour')
            ->pluck('n', 'jour')
            ->all();
    }

    /**
     * Tous les indicateurs d'une journee.
     *
     * @return array{count: int, first_at: ?CarbonImmutable, last_at: ?CarbonImmutable,
     *               numeric: array<int, array<string, mixed>>, textual: array<int, array<string, mixed>>}
     */
    public function forDay(Vehicle $vehicle, CarbonImmutable $day): array
    {
        $rows = VehicleTelemetry::where('vehicle_id', $vehicle->id)
            ->whereBetween('recorded_at', [$day->startOfDay(), $day->endOfDay()])
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'raw']);

        $numeric = [];
        $textual = [];

        foreach ($rows as $row) {
            $at = $row->recorded_at;

            foreach (($row->raw['telemetry'] ?? []) as $key => $value) {
                if ($value === null || in_array($key, self::METADATA, true)) {
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $numeric[$key][] = ['at' => $at, 'value' => (float) $value];

                    continue;
                }

                $shown = is_bool($value) ? ($value ? 'oui' : 'non') : (string) $value;
                $last = isset($textual[$key]) ? end($textual[$key]) : null;

                // Seuls les changements : un etat repete a l'identique pendant
                // trois mille releves n'apprend rien de plus que sa premiere
                // occurrence, et sa duree se lit sur l'ecart des horodatages.
                if ($last === null || $last['value'] !== $shown) {
                    $textual[$key][] = ['at' => $at, 'value' => $shown];
                }
            }
        }

        return [
            'count' => $rows->count(),
            'first_at' => $rows->first()?->recorded_at,
            'last_at' => $rows->last()?->recorded_at,
            'numeric' => $this->numericSeries($numeric),
            'textual' => $this->textualSeries($textual),
        ];
    }

    /**
     * @param  array<string, array<int, array{at: mixed, value: float}>>  $collected
     * @return array<int, array<string, mixed>>
     */
    private function numericSeries(array $collected): array
    {
        $series = [];

        foreach ($collected as $key => $points) {
            $values = array_column($points, 'value');
            $sampled = $this->sample($points);

            $series[] = [
                'key' => $key,
                'label' => self::KNOWN[$key][0] ?? $key,
                'unit' => self::KNOWN[$key][1] ?? null,
                'count' => count($points),
                'min' => min($values),
                'max' => max($values),
                'avg' => round(array_sum($values) / count($values), 2),
                'sampled' => count($sampled) < count($points),
                'labels' => array_map(fn ($p) => $p['at']->format('H:i:s'), $sampled),
                'values' => array_map(fn ($p) => $p['value'], $sampled),
            ];
        }

        usort($series, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $series;
    }

    /**
     * @param  array<string, array<int, array{at: mixed, value: string}>>  $collected
     * @return array<int, array<string, mixed>>
     */
    private function textualSeries(array $collected): array
    {
        $series = [];

        foreach ($collected as $key => $changes) {
            $series[] = [
                'key' => $key,
                'label' => self::KNOWN[$key][0] ?? $key,
                'changes' => $changes,
            ];
        }

        usort($series, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $series;
    }

    /**
     * Echantillonnage a pas constant : on garde la forme de la courbe sans
     * privilegier un moment de la journee.
     *
     * @param  array<int, array{at: mixed, value: float}>  $points
     * @return array<int, array{at: mixed, value: float}>
     */
    private function sample(array $points): array
    {
        $total = count($points);

        if ($total <= self::MAX_POINTS) {
            return $points;
        }

        $step = (int) ceil($total / self::MAX_POINTS);
        $kept = [];

        foreach ($points as $index => $point) {
            if ($index % $step === 0) {
                $kept[] = $point;
            }
        }

        // Le dernier point est conserve d'office : sans lui la courbe s'arrete
        // avant la fin de la journee.
        $kept[] = $points[$total - 1];

        return $kept;
    }
}
