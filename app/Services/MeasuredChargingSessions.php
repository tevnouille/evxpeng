<?php

namespace App\Services;

use App\Models\TelemetryChargingSession;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Recharges mesurees par le boitier OBD, ramenees a la forme des recharges
 * reconstituees par TelemetrySessionDetector.
 *
 * Meme forme de tableau des deux cotes : les vues n'ont pas a savoir d'ou vient
 * une session, seulement si elle est mesuree ou deduite. Ce service existe pour
 * que cette forme ne soit definie qu'une fois — elle etait sur le point d'etre
 * recopiee dans deux controleurs.
 */
class MeasuredChargingSessions
{
    public function __construct(private readonly ChargeContextGuesser $context)
    {
    }

    /**
     * @return Collection<int, TelemetryChargingSession>
     */
    public function forVehicle(Vehicle $vehicle, int $days): Collection
    {
        return TelemetryChargingSession::where('vehicle_id', $vehicle->id)
            ->where('started_at', '>=', now()->subDays($days))
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(TelemetryChargingSession $row, Vehicle $vehicle, bool $withContext = true): array
    {
        $socStart = $row->soc_start === null ? null : (float) $row->soc_start;
        $socEnd = $row->soc_end === null ? null : (float) $row->soc_end;

        return [
            'started_at' => $row->started_at,
            'ended_at' => $row->ended_at,
            'in_progress' => false,
            'inferred' => false,
            'measured' => true,
            'soc_start' => $socStart,
            'soc_end' => $socEnd,
            'soc_delta' => ($socStart === null || $socEnd === null) ? null : round($socEnd - $socStart, 1),
            // Energie relevee au compteur du BMS, non deduite du SoC.
            'kwh' => $row->energy_kwh === null ? null : (float) $row->energy_kwh,
            // Points de courbe plutot que releves : c'est la meme idee de
            // finesse d'echantillonnage, et la colonne les affiche pareil.
            'samples' => is_array($row->curve) ? count($row->curve) : 0,
            'duration_minutes' => (int) round(($row->duration_seconds ?? 0) / 60),
            'lat' => $row->lat,
            'lon' => $row->lon,
            'charging_type' => $row->charging_type,
            'max_power_kw' => $row->max_power_kw === null ? null : (float) $row->max_power_kw,
            'vehicle' => $vehicle,
            'context' => $withContext ? $this->context->guess($row->lat, $row->lon) : null,
        ];
    }

    /**
     * La session reconstituee recouvre-t-elle une session deja mesuree ?
     *
     * Sans ce filtre, la recharge du matin apparaitrait deux fois : une fois
     * mesuree par le boitier, une fois devinee depuis les relevés d'ABRP.
     *
     * @param  Collection<int, TelemetryChargingSession>  $measured
     * @param  array<string, mixed>  $session
     */
    public function overlaps(Collection $measured, array $session): bool
    {
        $start = $session['started_at'];
        $end = $session['ended_at'] ?? $start;

        return $measured->contains(function (TelemetryChargingSession $row) use ($start, $end) {
            $rowEnd = $row->ended_at ?? $row->started_at;

            return $start->lessThanOrEqualTo($rowEnd) && $end->greaterThanOrEqualTo($row->started_at);
        });
    }

    /**
     * Complete une session reconstituee des cles que portent les mesurees, pour
     * que les vues puissent les lire sans precaution.
     *
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    public function normalise(array $session): array
    {
        return $session + ['measured' => false, 'charging_type' => null, 'max_power_kw' => null];
    }
}
