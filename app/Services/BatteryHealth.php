<?php

namespace App\Services;

use App\Models\VehicleTelemetry;
use Illuminate\Support\Collection;

/**
 * Ecart entre la cellule la plus haute et la plus basse de la batterie, en
 * millivolts.
 *
 * Signal avant-coureur que le SoH ne donne pas : verifie sur cette flotte, il
 * reste a une seule valeur distincte (99,0 %) sur onze jours de releves — il
 * ne bougera pas assez vite pour se lire sur un graphique. L'ecart entre
 * cellules, lui, varie deja jour a jour ; un desequilibre qui se creuse s'y
 * verrait avant que le SoH ne bouge.
 *
 * Mediane et non derniere valeur : le boitier interroge les capteurs a tour
 * de role, les deux tensions d'un meme releve ne datent donc pas du meme
 * instant. Un releve sur cinquante donne meme un ecart negatif, physiquement
 * impossible — ecarte plutot que retenu.
 */
class BatteryHealth
{
    /**
     * En dessous, une mediane journaliere ne dit plus rien de fiable :
     * verifie sur les donnees reelles (2026-09-10), les jours a faible volume
     * (boitier reste peu de temps allume) sautent de plus de 10 mV sans
     * qu'aucun desequilibre reel ne l'explique.
     */
    private const MIN_DAILY_SAMPLES = 20;

    private static function gap(VehicleTelemetry $releve): ?float
    {
        $raw = $releve->raw['telemetry'] ?? [];
        $haute = isset($raw['HV_C_V_MAX']) && is_numeric($raw['HV_C_V_MAX']) ? (float) $raw['HV_C_V_MAX'] : null;
        $basse = isset($raw['HV_C_V_MIN']) && is_numeric($raw['HV_C_V_MIN']) ? (float) $raw['HV_C_V_MIN'] : null;

        if ($haute === null || $basse === null) {
            return null;
        }

        $gap = ($haute - $basse) * 1000;

        return $gap >= 0 ? $gap : null;
    }

    /**
     * Mediane sur l'ensemble des releves fournis, en millivolts.
     *
     * @param  Collection<int, VehicleTelemetry>  $history
     */
    public function medianGap(Collection $history): ?int
    {
        $gaps = $history
            ->map(self::gap(...))
            ->filter(fn (?float $g) => $g !== null)
            ->sort()
            ->values();

        return $gaps->isEmpty() ? null : (int) round($gaps[intdiv($gaps->count(), 2)]);
    }

    /**
     * Mediane jour par jour, pour une courbe d'evolution. Les jours sous le
     * seuil d'echantillonnage sont ecartes plutot que d'ajouter du bruit a la
     * courbe — mieux vaut un trou qu'un point qui ne veut rien dire.
     *
     * @param  Collection<int, VehicleTelemetry>  $history  Tries ou non, peu importe.
     * @return array<int, array{date: string, median: int, count: int}> Par date croissante.
     */
    public function dailyMedianGap(Collection $history): array
    {
        $byDay = $history->groupBy(
            fn (VehicleTelemetry $row) => $row->recorded_at->timezone(config('app.timezone'))->format('Y-m-d')
        );

        $result = [];

        foreach ($byDay as $day => $rows) {
            $gaps = $rows
                ->map(self::gap(...))
                ->filter(fn (?float $g) => $g !== null)
                ->sort()
                ->values();

            if ($gaps->count() < self::MIN_DAILY_SAMPLES) {
                continue;
            }

            $result[] = [
                'date' => $day,
                'median' => (int) round($gaps[intdiv($gaps->count(), 2)]),
                'count' => $gaps->count(),
            ];
        }

        usort($result, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $result;
    }
}
