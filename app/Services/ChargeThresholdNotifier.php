<?php

namespace App\Services;

use App\Models\ChargeAlert;
use App\Models\Vehicle;
use App\Models\VehicleTelemetry;

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
        $session = $this->currentSession($vehicle);

        // Reference : le niveau au branchement. Un palier deja franchi avant la
        // charge n'a pas a etre notifie — brancher a 96 % ne doit pas declencher
        // 79, 89 et 95.
        $socAtStart = $session['soc_start'] ?? $soc;
        $sessionStart = $session['started_at'] ?? $current->recorded_at;

        $due = array_values(array_filter(
            array_map('intval', $thresholds),
            fn (int $threshold) => $threshold > $socAtStart && $threshold <= $soc
        ));

        if ($due === []) {
            return [];
        }

        // On se fie a la livraison, pas a la simple existence de la ligne : un
        // envoi echoue (reseau coupe, 500 chez Free) doit etre retente au releve
        // suivant plutot que d'etre perdu.
        $delivered = ChargeAlert::where('vehicle_id', $vehicle->id)
            ->where('session_started_at', $sessionStart)
            ->where('delivered', true)
            ->pluck('threshold')
            ->all();

        $pending = array_values(array_diff($due, array_map('intval', $delivered)));

        if ($pending === []) {
            return [];
        }

        sort($pending);

        // Plusieurs paliers d'un coup (trou de telemetrie, ou reprise apres un
        // echec) ne donnent qu'un SMS : celui du palier le plus haut.
        $sent = $this->sms->send($this->message($vehicle, $current, $pending, $soc));

        foreach ($pending as $threshold) {
            ChargeAlert::updateOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'session_started_at' => $sessionStart,
                    'threshold' => $threshold,
                ],
                ['soc' => $soc, 'delivered' => $sent]
            );
        }

        return $sent ? $pending : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentSession(Vehicle $vehicle): ?array
    {
        $rows = $vehicle->telemetries()
            ->where('recorded_at', '>=', now()->subDay())
            ->orderBy('recorded_at')
            ->get();

        return collect($this->detector->detect($rows, null))->firstWhere('in_progress', true);
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

}
