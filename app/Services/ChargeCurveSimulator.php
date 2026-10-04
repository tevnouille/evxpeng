<?php

namespace App\Services;

/**
 * Modele de temps de charge, partage entre la page "Courbe de recharge" et le
 * planificateur.
 *
 * Principe du bridage : a energie egale, une puissance divisee par deux double
 * la duree du segment. Chaque intervalle de la courbe de reference est donc
 * etire du rapport entre sa puissance d'origine et la puissance reellement
 * disponible. Sans plafond, on retrouve exactement les durees publiees par
 * evkx.net.
 */
class ChargeCurveSimulator
{
    /**
     * Duree cumulee depuis 0 %, en secondes, pour chaque point de la courbe.
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, int>
     */
    public function cumulativeSeconds(array $points, ?float $cap = null): array
    {
        $seconds = [];
        $cumulative = 0.0;
        $previous = null;

        foreach ($points as $index => $point) {
            $kw = (float) $point['kw'];

            if ($previous !== null) {
                $elapsed = $this->toSeconds($point['time']) - $this->toSeconds($previous['time']);
                // La puissance representative du segment est la moyenne de ses
                // bornes : c'est elle qu'encode implicitement la duree relevee.
                $average = ((float) $previous['kw'] + $kw) / 2;
                $limited = $cap !== null ? min($cap, $average) : $average;

                $cumulative += $limited > 0 ? $elapsed * ($average / $limited) : 0;
            }

            $seconds[$index] = (int) round($cumulative);
            $previous = $point;
        }

        return $seconds;
    }

    /**
     * Duree pour passer d'un niveau a un autre, interpolee entre les paliers
     * entiers de la courbe.
     *
     * @param  array<string, mixed>  $curve
     */
    public function duration(array $curve, float $from, float $to, ?float $cap = null): ?int
    {
        if ($to <= $from) {
            return 0;
        }

        $points = $curve['points'] ?? [];

        if ($points === []) {
            return null;
        }

        $seconds = $this->cumulativeSeconds($points, $cap);
        $bySoc = [];

        foreach ($points as $index => $point) {
            $bySoc[(int) $point['soc']] = $seconds[$index];
        }

        $start = $this->interpolate($bySoc, $from);
        $end = $this->interpolate($bySoc, $to);

        if ($start === null || $end === null) {
            return null;
        }

        return max(0, (int) round($end - $start));
    }

    /**
     * Energie qu'il faut envoyer dans la batterie entre deux niveaux, cote
     * batterie (les pertes de la borne ne sont pas modelisees).
     *
     * @param  array<string, mixed>  $curve
     */
    public function energy(array $curve, float $from, float $to): float
    {
        $capacity = (float) ($curve['battery_net_kwh'] ?? $curve['battery_kwh'] ?? 0);

        return round(max(0.0, $to - $from) * $capacity / 100, 1);
    }

    /**
     * Puissance moyenne effective sur la plage, en kW. Sert a expliquer un arret
     * long : 22 kW sur une borne AC, ce n'est pas la meme chose que 150 kW.
     *
     * @param  array<string, mixed>  $curve
     */
    public function averagePower(array $curve, float $from, float $to, ?float $cap = null): ?float
    {
        $seconds = $this->duration($curve, $from, $to, $cap);

        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        return round($this->energy($curve, $from, $to) / ($seconds / 3600), 1);
    }

    /**
     * @param  array<int, int>  $bySoc
     */
    private function interpolate(array $bySoc, float $soc): ?float
    {
        $soc = max(0.0, min(100.0, $soc));
        $low = (int) floor($soc);
        $high = (int) ceil($soc);

        if (! isset($bySoc[$low]) || ! isset($bySoc[$high])) {
            return null;
        }

        if ($low === $high) {
            return (float) $bySoc[$low];
        }

        return $bySoc[$low] + ($bySoc[$high] - $bySoc[$low]) * ($soc - $low);
    }

    private function toSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(array_map('intval', explode(':', $time)), 3, 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }
}
