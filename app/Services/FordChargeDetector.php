<?php

namespace App\Services;

use App\Models\FordTelemetry;
use Illuminate\Support\Collection;

/**
 * Detecte les recharges dans les releves Ford (ford_telemetries), a partir
 * d'un signal direct cette fois -- contrairement a Xpeng
 * (App\Services\XpengChargeDetector), qui doit deduire la charge d'une
 * vitesse nulle et d'une puissance/SoC en hausse faute d'indicateur fiable :
 * Ford publie charge_display_status="IN_PROGRESS" explicitement.
 */
class FordChargeDetector
{
    /**
     * Au-dela, deux episodes sont consideres distincts plutot qu'une seule
     * recharge interrompue. Plus large que la tolerance equivalente cote
     * Xpeng (10 min) : ford:sync tourne toutes les 30 min (contre un releve
     * a la minute chez Xpeng), un seul passage manque et l'ecart entre deux
     * releves "en charge" consecutifs depasserait deja 30 min sans que la
     * charge se soit reellement interrompue.
     */
    private const TOLERANCE_MINUTES = 90;

    /**
     * @param  Collection<int, FordTelemetry>  $releves  Deja tries par recorded_at croissant.
     * @param  ?float  $capaciteBatterie  Capacite estimee a 100% (voir FordDataController::capaciteBatterieMoyenne7j), pour convertir un delta de SoC en kWh.
     * @return array<int, array{debut: \Illuminate\Support\Carbon, fin: \Illuminate\Support\Carbon, duree_minutes: int, soc_debut: ?float, soc_fin: ?float, puissance_max_kw: float, energie_kwh: ?float}>
     */
    public function detecter(Collection $releves, ?float $capaciteBatterie): array
    {
        $sessions = [];
        $courante = null;

        foreach ($releves as $releve) {
            if ($releve->charge_display_status !== 'IN_PROGRESS') {
                continue;
            }

            if ($courante !== null && abs($releve->recorded_at->diffInMinutes($courante['fin'])) > self::TOLERANCE_MINUTES) {
                $sessions[] = $this->finaliser($courante, $capaciteBatterie);
                $courante = null;
            }

            $courante ??= [
                'debut' => $releve->recorded_at,
                'fin' => $releve->recorded_at,
                'soc_debut' => $releve->xev_soc,
                'soc_fin' => $releve->xev_soc,
                'puissance_max_kw' => 0.0,
            ];

            $courante['fin'] = $releve->recorded_at;
            $courante['soc_fin'] = $releve->xev_soc ?? $courante['soc_fin'];

            // Pas de puissance publiee directement : reconstruite depuis
            // tension x courant de sortie du chargeur (xevBatteryCharger*Output).
            if ($releve->xev_charger_voltage_output_v !== null && $releve->xev_charger_current_output_a !== null) {
                $puissance = $releve->xev_charger_voltage_output_v * $releve->xev_charger_current_output_a / 1000;
                $courante['puissance_max_kw'] = max($courante['puissance_max_kw'], $puissance);
            }
        }

        if ($courante !== null) {
            $sessions[] = $this->finaliser($courante, $capaciteBatterie);
        }

        return array_reverse($sessions);
    }

    /**
     * @param  array<string, mixed>  $courante
     * @return array<string, mixed>
     */
    private function finaliser(array $courante, ?float $capaciteBatterie): array
    {
        $courante['duree_minutes'] = abs($courante['debut']->diffInMinutes($courante['fin'])) + 1;

        // xevBatteryChargerEnergyOutput existe mais sa portee (cumul sur la
        // session ? a vie ?) n'est pas confirmee -- delta de SoC x capacite
        // estimee est le calcul le plus sur avec ce qu'on sait aujourd'hui.
        $courante['energie_kwh'] = ($capaciteBatterie && $courante['soc_debut'] !== null && $courante['soc_fin'] !== null)
            ? round(max(0, $courante['soc_fin'] - $courante['soc_debut']) / 100 * $capaciteBatterie, 1)
            : null;

        return $courante;
    }
}
