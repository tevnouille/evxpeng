<?php

namespace App\Services;

use App\Models\TelemetryChargingSession;
use App\Models\Vehicle;

/**
 * Courbe de recharge reconstituee depuis ce que la voiture a reellement accepte.
 *
 * Les courbes de reference viennent d'evkx.net : un exemplaire, un jour, sur une
 * borne donnee. Le boitier OBD, lui, releve la puissance seconde par seconde a
 * chaque charge — c'est la courbe de *cette* voiture, avec son vieillissement et
 * ses conditions.
 *
 * Deux garde-fous, sans lesquels la substitution nuirait plus qu'elle n'aide :
 *
 *  - **Seules les charges rapides comptent.** En courant alternatif, la puissance
 *    est imposee par la borne et le chargeur embarque, jamais par la batterie :
 *    une charge a 9,9 kW ne dit rien de ce que la batterie accepte a 150 kW.
 *    L'injecter creuserait un trou dans la courbe et ferait mentir les temps de
 *    charge du planificateur.
 *  - **Une couverture minimale.** Quelques points epars entre deux paliers
 *    theoriques produiraient des ruptures, et le simulateur integre la courbe
 *    palier par palier. En dessous du seuil, on n'affiche les mesures qu'a titre
 *    indicatif sans toucher a la courbe de reference.
 */
class MeasuredChargingCurve
{
    /**
     * Nombre de paliers de SoC distincts a couvrir avant de remplacer quoi que
     * ce soit dans la courbe de reference.
     */
    private const MIN_BUCKETS = 10;

    /**
     * Marge de tolerance au-dessus du maximum annonce par le constructeur : une
     * lecture au-dela est une aberration de mesure, pas un exploit.
     */
    private const IMPLAUSIBLE_FACTOR = 1.15;

    /**
     * Puissance retenue par palier de SoC, en kW.
     *
     * Le maximum et non la moyenne : on cherche ce que la batterie *accepte*.
     * Une session bridee par une borne faible tirerait la moyenne vers le bas et
     * ferait croire a une degradation qui n'existe pas.
     *
     * @return array{
     *     points: array<int, float>,
     *     samples: array<int, int>,
     *     sessions: int,
     *     buckets: int,
     *     applied: bool,
     *     min_soc: int|null,
     *     max_soc: int|null,
     *     last_at: \Illuminate\Support\Carbon|null
     * }
     */
    public function forVehicle(Vehicle $vehicle, ?float $maxPowerKw = null): array
    {
        $sessions = TelemetryChargingSession::where('vehicle_id', $vehicle->id)
            ->where('charging_type', 'dc')
            ->whereNotNull('curve')
            ->orderBy('started_at')
            ->get();

        $ceiling = $maxPowerKw === null ? null : $maxPowerKw * self::IMPLAUSIBLE_FACTOR;
        $points = [];
        $samples = [];

        foreach ($sessions as $session) {
            foreach ($session->curve ?? [] as $point) {
                $soc = $point['soc'] ?? null;
                $kw = $point['powerKw'] ?? null;

                if ($soc === null || $kw === null) {
                    continue;
                }

                $kw = abs((float) $kw);
                $bucket = (int) round((float) $soc);

                if ($bucket < 0 || $bucket > 100 || ($ceiling !== null && $kw > $ceiling)) {
                    continue;
                }

                $points[$bucket] = max($points[$bucket] ?? 0.0, round($kw, 1));
                $samples[$bucket] = ($samples[$bucket] ?? 0) + 1;
            }
        }

        ksort($points);
        ksort($samples);

        $keys = array_keys($points);

        return [
            'points' => $points,
            'samples' => $samples,
            'sessions' => $sessions->count(),
            'buckets' => count($points),
            'applied' => count($points) >= self::MIN_BUCKETS,
            'min_soc' => $keys === [] ? null : min($keys),
            'max_soc' => $keys === [] ? null : max($keys),
            'last_at' => $sessions->last()?->started_at,
        ];
    }

    /**
     * Substitue les puissances mesurees dans les paliers de la courbe de
     * reference, et marque chaque palier de sa provenance.
     *
     * La courbe garde toujours ses 101 paliers : les paliers non couverts
     * conservent la valeur theorique, sans quoi le simulateur perdrait la
     * continuite dont il a besoin pour integrer une duree.
     *
     * @param  array<string, mixed>  $curve
     * @param  array<string, mixed>  $measured
     * @return array<string, mixed>
     */
    public function apply(array $curve, array $measured): array
    {
        $curve['measured'] = $measured;

        if (! ($measured['applied'] ?? false)) {
            return $curve;
        }

        foreach ($curve['points'] as $index => $point) {
            $soc = (int) ($point['soc'] ?? -1);

            if (! isset($measured['points'][$soc])) {
                continue;
            }

            $curve['points'][$index]['kw_reference'] = $point['kw'];
            $curve['points'][$index]['kw'] = $measured['points'][$soc];
            $curve['points'][$index]['measured'] = true;
        }

        return $curve;
    }
}
