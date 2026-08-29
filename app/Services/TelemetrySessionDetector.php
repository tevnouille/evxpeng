<?php

namespace App\Services;

use App\Models\VehicleTelemetry;
use Illuminate\Support\Collection;

/**
 * Reconstitue des sessions de recharge a partir des releves de telemetrie.
 *
 * ABRP n'expose aucune notion de session : on ne dispose que d'une suite de
 * points portant un booleen "en charge". Une session est donc encadree par la
 * transition false -> true puis true -> false.
 *
 * L'energie deduite est celle *entree dans la batterie* (delta de SoC x capacite
 * utile). Elle est structurellement inferieure a l'energie *facturee a la borne*,
 * qui inclut les pertes de charge. C'est une aide a la saisie, pas un releve.
 */
class TelemetrySessionDetector
{
    /**
     * Au-dela de ce delai, le point qui precede la charge est trop ancien pour
     * donner un niveau de depart credible : on part alors du premier point
     * effectivement observe en charge.
     */
    private const MAX_GAP_MINUTES = 30;

    /**
     * @param  Collection<int, VehicleTelemetry>  $rows  Tries par recorded_at croissant.
     * @return array<int, array<string, mixed>>  Les plus recentes d'abord.
     */
    public function detect(Collection $rows, ?float $netCapacityKwh): array
    {
        $sessions = [];
        $currentPoints = null;
        $startRow = null;
        $previous = null;

        foreach ($rows as $row) {
            if ($row->is_charging) {
                if ($currentPoints === null) {
                    $startRow = $this->startingPoint($previous, $row);
                    $currentPoints = [];
                }

                $currentPoints[] = $row;
            } elseif ($currentPoints !== null) {
                // $row est le premier point non-charge : c'est lui qui porte le
                // niveau atteint en fin de charge.
                $sessions[] = $this->build($startRow, $currentPoints, $row, $netCapacityKwh, false);
                $currentPoints = null;
                $startRow = null;
            }

            $previous = $row;
        }

        if ($currentPoints !== null) {
            $sessions[] = $this->build($startRow, $currentPoints, null, $netCapacityKwh, true);
        }

        return array_reverse($sessions);
    }

    private function startingPoint(?VehicleTelemetry $previous, VehicleTelemetry $first): VehicleTelemetry
    {
        if ($previous === null) {
            return $first;
        }

        return $previous->recorded_at->diffInMinutes($first->recorded_at) <= self::MAX_GAP_MINUTES
            ? $previous
            : $first;
    }

    /**
     * @param  array<int, VehicleTelemetry>  $points
     * @return array<string, mixed>
     */
    private function build(
        VehicleTelemetry $start,
        array $points,
        ?VehicleTelemetry $end,
        ?float $netCapacityKwh,
        bool $inProgress
    ): array {
        $last = $end ?? $points[count($points) - 1];

        $socStart = $start->soc !== null ? (float) $start->soc : null;
        $socEnd = $last->soc !== null ? (float) $last->soc : null;

        $delta = ($socStart !== null && $socEnd !== null) ? max(0.0, $socEnd - $socStart) : null;

        return [
            'started_at' => $start->recorded_at,
            'ended_at' => $inProgress ? null : $last->recorded_at,
            'in_progress' => $inProgress,
            'soc_start' => $socStart,
            'soc_end' => $socEnd,
            'soc_delta' => $delta,
            'kwh' => ($delta !== null && $netCapacityKwh) ? round($delta / 100 * $netCapacityKwh, 2) : null,
            // Nombre de releves reellement observes en charge : sert a signaler
            // une session mal echantillonnee (un seul point = bornes incertaines).
            'samples' => count($points),
            'duration_minutes' => $start->recorded_at->diffInMinutes($last->recorded_at),
            'lat' => $last->lat,
            'lon' => $last->lon,
        ];
    }
}
