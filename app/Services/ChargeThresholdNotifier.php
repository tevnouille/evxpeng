<?php

namespace App\Services;

use App\Models\ChargeAlert;
use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Previent par SMS quand le niveau de charge franchit certains paliers.
 *
 * On notifie sur le *franchissement* : un palier n'est envoye que s'il se situe
 * entre le releve precedent et le releve courant. Sans cette regle, brancher la
 * voiture a 96 % declencherait d'un coup tous les paliers inferieurs.
 */
class ChargeThresholdNotifier
{
    public function __construct(
        private readonly FreeMobileSms $sms,
        private readonly TelemetrySessionDetector $detector,
        private readonly ChargingCurveRepository $curves,
    ) {
    }

    /**
     * @return array<int, int>  Paliers effectivement notifies.
     */
    public function notify(Vehicle $vehicle, VehicleTelemetry $current): array
    {
        $thresholds = config('services.charge_alerts.thresholds', []);

        if (! $this->sms->configured() || $thresholds === [] || ! $current->is_charging || $current->soc === null) {
            return [];
        }

        $soc = (float) $current->soc;

        $previous = $vehicle->telemetries()
            ->where('recorded_at', '<', $current->recorded_at)
            ->orderByDesc('recorded_at')
            ->first();

        // Premier releve connu de la voiture : aucun franchissement observable,
        // on se contente d'etablir la reference.
        if ($previous === null || $previous->soc === null) {
            return [];
        }

        $crossed = array_values(array_filter(
            $thresholds,
            fn ($threshold) => $threshold > (float) $previous->soc && $threshold <= $soc
        ));

        if ($crossed === []) {
            return [];
        }

        $sessionStart = $this->sessionStart($vehicle, $current);

        $fresh = array_values(array_filter(
            array_map('intval', $crossed),
            fn (int $threshold) => $this->reserve($vehicle, $sessionStart, $threshold, $soc)
        ));

        if ($fresh === []) {
            return [];
        }

        sort($fresh);

        // Un trou de telemetrie peut faire franchir plusieurs paliers d'un coup
        // (80 % puis 96 % au releve suivant). On n'envoie alors qu'un seul SMS,
        // celui du palier le plus haut, en mentionnant les autres.
        $delivered = $this->sms->send($this->message($vehicle, $current, $fresh, $soc));

        ChargeAlert::where('vehicle_id', $vehicle->id)
            ->where('session_started_at', $sessionStart)
            ->whereIn('threshold', $fresh)
            ->update(['delivered' => $delivered]);

        return $delivered ? $fresh : [];
    }

    /**
     * Reserve le palier avant tout envoi : la contrainte d'unicite fait office de
     * verrou, deux executions concurrentes ne peuvent donc pas notifier deux fois
     * le meme palier de la meme charge.
     */
    private function reserve(Vehicle $vehicle, Carbon $sessionStart, int $threshold, float $soc): bool
    {
        try {
            ChargeAlert::create([
                'vehicle_id' => $vehicle->id,
                'session_started_at' => $sessionStart,
                'threshold' => $threshold,
                'soc' => $soc,
            ]);
        } catch (QueryException $e) {
            // Palier deja notifie pour cette charge.
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, int>  $thresholds  Tries par ordre croissant.
     */
    private function message(Vehicle $vehicle, VehicleTelemetry $current, array $thresholds, float $soc): string
    {
        $threshold = end($thresholds);

        $parts = [sprintf('%s : %d %% atteint', $vehicle->name, $threshold)];

        if ($threshold >= 100) {
            $parts[0] = sprintf('%s : charge terminee (100 %%)', $vehicle->name);
        }

        if (count($thresholds) > 1) {
            $lower = array_slice($thresholds, 0, -1);
            $parts[0] .= sprintf(' (paliers %s franchis sans releve intermediaire)', implode(', ', $lower));
        }

        if ($current->power_kw !== null) {
            $parts[] = sprintf('%s kW', str_replace('.', ',', (string) round(abs((float) $current->power_kw), 1)));
        }

        $remaining = $this->remainingMinutes($vehicle, $current, $soc);

        if ($remaining !== null) {
            $parts[] = sprintf('~%d min jusqu\'a 100 %%', $remaining);
        }

        return implode(' - ', $parts);
    }

    /**
     * Estimation a la puissance mesuree, la seule dont on dispose au moment de
     * l'envoi sans rejouer toute la courbe.
     */
    private function remainingMinutes(Vehicle $vehicle, VehicleTelemetry $current, float $soc): ?int
    {
        $power = $current->power_kw === null ? null : abs((float) $current->power_kw);
        $curve = $vehicle->charging_curve ? $this->curves->find($vehicle->charging_curve) : null;

        if ($power === null || $power <= 0 || $curve === null || $soc >= 100) {
            return null;
        }

        return (int) round((100 - $soc) / 100 * (float) $curve['battery_net_kwh'] / $power * 60);
    }

    private function sessionStart(Vehicle $vehicle, VehicleTelemetry $current): Carbon
    {
        $rows = $vehicle->telemetries()
            ->where('recorded_at', '>=', now()->subDay())
            ->orderBy('recorded_at')
            ->get();

        $session = collect($this->detector->detect($rows, null))->firstWhere('in_progress', true);

        return $session['started_at'] ?? $current->recorded_at;
    }
}
