<?php

namespace App\Services;

use App\Models\TelemetryChargingSession;
use App\Models\Vehicle;

/**
 * Previent par SMS quand une recharge terminee s'ecarte significativement de
 * la courbe de reference du vehicule.
 *
 * Meme jugement que la page « Recharges face à la courbe »
 * (App\Services\ChargeCurveComparison) : jusqu'ici il fallait visiter cette
 * page pour le decouvrir. Appele une fois par recharge, au moment ou le
 * boitier publie la session terminee (App\Console\Commands\IngestMqttTelemetry) —
 * pas de sondage separe.
 */
class ChargeGapNotifier
{
    public function __construct(
        private readonly FreeMobileSms $sms,
        private readonly ChargingCurveRepository $curves,
        private readonly ChargeCurveComparison $comparison,
    ) {
    }

    /**
     * @return bool  Un SMS est-il parti ?
     */
    public function notify(Vehicle $vehicle, TelemetryChargingSession $session): bool
    {
        if ($session->gap_alert_delivered) {
            return false;
        }

        if ($session->energy_kwh === null || (float) $session->energy_kwh < ChargeCurveComparison::COMPARE_MIN_KWH) {
            return false;
        }

        $curve = $vehicle->charging_curve ? $this->curves->find($vehicle->charging_curve) : null;

        if ($curve === null || $session->curve === null) {
            return false;
        }

        $result = $this->comparison->compare($session, $curve);
        $worst = $result['worst'];

        if ($worst === null || ! $worst['significant']) {
            return false;
        }

        // Le SMS part sur le compte Free du proprietaire du vehicule, jamais sur
        // un compte commun (meme regle que ChargeThresholdNotifier).
        $owner = $vehicle->user;
        $sms = $owner ? $this->sms->forUser($owner) : null;

        if ($sms === null || ! $sms->configured()) {
            return false;
        }

        $sent = $sms->send($this->message($vehicle, $session, $worst));

        if ($sent) {
            $session->forceFill(['gap_alert_delivered' => true])->save();
        }

        return $sent;
    }

    /**
     * @param  array{soc: float, measured: float, expected: float, gap: float, significant: bool}  $worst
     */
    private function message(Vehicle $vehicle, TelemetryChargingSession $session, array $worst): string
    {
        $ratio = $worst['expected'] > 0 ? (int) round($worst['gap'] / $worst['expected'] * 100) : null;
        $ratioText = $ratio === null ? '' : sprintf(' (-%d %%)', $ratio);

        return sprintf(
            '%s : écart de charge important. %s kW mesurés contre %s attendus à %d %%%s. '
            .'Recharge du %s, %s kWh. Détail : /courbe-de-recharge/comparaison',
            $vehicle->name,
            str_replace('.', ',', (string) $worst['measured']),
            str_replace('.', ',', (string) $worst['expected']),
            (int) round($worst['soc']),
            $ratioText,
            $session->started_at->format('d/m H:i'),
            str_replace('.', ',', (string) round((float) $session->energy_kwh, 1))
        );
    }
}
