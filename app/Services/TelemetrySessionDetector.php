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
 * Ce booleen suppose toutefois que la voiture ait remonte quelque chose pendant
 * la charge. Sans reseau, ou dongle OBD debranche, la charge entiere se joue
 * entre deux releves et aucun point ne la porte : elle ne se lit plus que comme
 * un niveau de batterie qui a monte a l'arret. Ces charges-la sont deduites
 * (`inferred`), avec des bornes de temps beaucoup plus lâches — voir
 * detectSilent().
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
     * Hausse de niveau, en points, au-dela de laquelle une recharge non
     * observee devient l'explication la plus simple. En dessous, la remontee
     * s'explique par la derive de la jauge : on a vu le SoC repasser de 92,0 a
     * 92,2 sans que rien ne se passe.
     */
    private const SILENT_MIN_RISE = 2.0;

    /**
     * Tolerance sur l'odometre, en km. A l'arret, une hausse du niveau ne peut
     * pas venir de la recuperation au freinage : c'est ce qui distingue la
     * charge silencieuse d'une longue descente.
     */
    private const SILENT_MAX_KM = 1.0;

    /**
     * Sans odometre — le cloud constructeur seul n'en fournit pas — on ne peut
     * pas prouver que la voiture n'a pas roule. On n'ose alors la deduction que
     * sur une hausse qu'aucune regeneration ne produirait.
     */
    private const SILENT_BLIND_MIN_RISE = 10.0;

    /**
     * Au-dela d'une journee sans releve, parler d'« une » recharge n'a plus de
     * sens : il a pu s'en produire plusieurs. On laisse alors la saisie
     * manuelle plutot que de proposer une session inventee.
     */
    private const SILENT_MAX_GAP_HOURS = 24;

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
            } elseif ($previous !== null && ! $previous->is_charging && $this->isSilentCharge($previous, $row)) {
                $sessions[] = $this->buildSilent($previous, $row, $netCapacityKwh);
            }

            $previous = $row;
        }

        if ($currentPoints !== null) {
            $sessions[] = $this->build($startRow, $currentPoints, null, $netCapacityKwh, true);
        }

        return array_reverse($sessions);
    }

    /**
     * Deux releves consecutifs trahissent-ils une charge qu'aucun point n'a vue ?
     */
    private function isSilentCharge(VehicleTelemetry $before, VehicleTelemetry $after): bool
    {
        if ($before->soc === null || $after->soc === null) {
            return false;
        }

        $rise = (float) $after->soc - (float) $before->soc;

        if ($before->recorded_at->diffInHours($after->recorded_at) > self::SILENT_MAX_GAP_HOURS) {
            return false;
        }

        if ($before->odometer === null || $after->odometer === null) {
            return $rise >= self::SILENT_BLIND_MIN_RISE;
        }

        // La voiture n'a pas bouge : la seule energie qui a pu entrer vient
        // d'une prise.
        return $rise >= self::SILENT_MIN_RISE
            && (float) $after->odometer - (float) $before->odometer <= self::SILENT_MAX_KM;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSilent(VehicleTelemetry $before, VehicleTelemetry $after, ?float $netCapacityKwh): array
    {
        $socStart = (float) $before->soc;
        $socEnd = (float) $after->soc;
        $delta = $socEnd - $socStart;

        return [
            'started_at' => $before->recorded_at,
            'ended_at' => $after->recorded_at,
            'in_progress' => false,
            // La charge s'est produite quelque part dans cet intervalle, sans
            // qu'on sache ou : la duree ci-dessous est celle du trou de mesure,
            // pas celle du branchement. Les vues ne la pre-remplissent donc pas.
            'inferred' => true,
            'soc_start' => $socStart,
            'soc_end' => $socEnd,
            'soc_delta' => $delta,
            'kwh' => $netCapacityKwh ? round($delta / 100 * $netCapacityKwh, 2) : null,
            'samples' => 0,
            'duration_minutes' => (int) round($before->recorded_at->diffInMinutes($after->recorded_at)),
            // Position du releve d'apres : l'odometre prouve que la voiture n'a
            // pas bouge, c'est donc bien le lieu de la charge.
            'lat' => $after->lat,
            'lon' => $after->lon,
        ];
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
            'inferred' => false,
            'soc_start' => $socStart,
            'soc_end' => $socEnd,
            'soc_delta' => $delta,
            'kwh' => ($delta !== null && $netCapacityKwh) ? round($delta / 100 * $netCapacityKwh, 2) : null,
            // Nombre de releves reellement observes en charge : sert a signaler
            // une session mal echantillonnee (un seul point = bornes incertaines).
            'samples' => count($points),
            'duration_minutes' => (int) round($start->recorded_at->diffInMinutes($last->recorded_at)),
            'lat' => $last->lat,
            'lon' => $last->lon,
        ];
    }
}
