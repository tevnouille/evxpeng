<?php

namespace App\Services;

use App\Models\TelemetryChargingSession;

/**
 * Superpose une recharge mesuree et la courbe de reference du vehicule.
 *
 * Extrait de ChargingCurveController, seul appelant jusqu'ici : la page
 * « Recharges face à la courbe » et l'alerte SMS sur ecart significatif
 * (ChargeGapNotifier) doivent juger le meme ecart de la meme facon — deux
 * implementations de la meme formule finiraient par diverger, comme ailleurs
 * dans l'application.
 */
class ChargeCurveComparison
{
    /**
     * Energie minimale pour qu'une recharge merite d'etre confrontee a la courbe.
     *
     * En dessous, la session ne couvre que quelques points de SoC : on y verrait
     * un fragment de courbe, pas un comportement. Le seuil est bas a dessein —
     * une charge rapide de quelques kWh couvre deja assez de paliers pour qu'un
     * bridage se voie, et c'est justement celle-la qu'on veut examiner.
     */
    public const COMPARE_MIN_KWH = 5.0;

    /**
     * Duree de montee en puissance ecartee du calcul de l'ecart.
     *
     * Une charge rapide ne demarre pas a sa pleine puissance : la borne et la
     * voiture negocient, le courant monte en quelques dizaines de secondes. Sans
     * cette exclusion, le premier point de chaque session serait signale comme
     * le pire defaut — on a mesure 35,7 kW contre 135 attendus sur une charge
     * qui a ensuite tenu 160 kW.
     */
    private const RAMP_UP_SECONDS = 90;

    /**
     * Puissance en dessous de laquelle un point ne mesure plus une charge.
     *
     * Le dernier releve d'une session, ou une pause en cours de route, tombe a
     * zero : le confronter a la courbe signalait « 78 kW de moins » sur une
     * charge terminee normalement.
     */
    private const IDLE_KW = 1.0;

    /**
     * A partir de quand un ecart merite d'etre signale comme un defaut.
     *
     * Une charge ne suit jamais la courbe au kilowatt pres, et la courbe decrit
     * un exemplaire du modele, pas cette voiture-la. Alerter sur trois
     * kilowatts d'ecart apprendrait a ignorer l'alerte.
     */
    public const SIGNIFICANT_GAP_RATIO = 0.15;

    public const SIGNIFICANT_GAP_KW = 10.0;

    /**
     * Superposition d'une session et de la courbe, sur la plage qu'elle couvre.
     *
     * En courant alternatif, la puissance est imposee par la borne et le
     * chargeur embarque : la confronter aux 300 kW de la courbe ne dirait rien
     * de la batterie. On bride alors la reference a ce que la borne a delivre,
     * et la question devient « la puissance a-t-elle tenu ? ».
     *
     * @param  array<string, mixed>  $curve
     * @return array<string, mixed>
     */
    public function compare(TelemetryChargingSession $session, array $curve): array
    {
        $measured = [];

        $firstAt = null;

        foreach ($session->curve ?? [] as $point) {
            if (! isset($point['soc'], $point['powerKw'])) {
                continue;
            }

            // Les horodatages sont en millisecondes depuis l'epoque.
            $at = isset($point['timestamp']) ? (float) $point['timestamp'] / 1000 : null;
            $firstAt ??= $at;

            $measured[] = [
                'x' => round((float) $point['soc'], 1),
                'y' => round(abs((float) $point['powerKw']), 1),
                'ramp' => ($at !== null && $firstAt !== null) && ($at - $firstAt) < self::RAMP_UP_SECONDS,
            ];
        }

        $socs = array_column($measured, 'x');
        $low = $socs === [] ? 0 : max(0, (int) floor(min($socs)) - 2);
        $high = $socs === [] ? 100 : min(100, (int) ceil(max($socs)) + 2);

        $alternating = $session->charging_type === 'ac';
        $cap = $alternating && $session->max_power_kw ? (float) $session->max_power_kw : null;

        $reference = [];

        foreach ($curve['points'] as $point) {
            $soc = (int) $point['soc'];

            if ($soc < $low || $soc > $high) {
                continue;
            }

            $kw = (float) $point['kw'];
            $reference[] = ['x' => $soc, 'y' => round($cap === null ? $kw : min($cap, $kw), 1)];
        }

        // Ecart le plus marque entre ce que la batterie aurait pu accepter et ce
        // qu'elle a recu : c'est lui qui signale un defaut, pas la moyenne.
        $bySoc = array_column($reference, 'y', 'x');
        $worst = null;

        foreach ($measured as $point) {
            $expected = $bySoc[(int) round($point['x'])] ?? null;

            // La montee en puissance du debut et les points sans courant — fin
            // de session, pause — ne disent rien de ce que la batterie accepte.
            if ($expected === null || $expected <= 0 || $point['ramp'] || $point['y'] < self::IDLE_KW) {
                continue;
            }

            $gap = $expected - $point['y'];

            if ($worst === null || $gap > $worst['gap']) {
                $worst = [
                    'soc' => $point['x'],
                    'measured' => $point['y'],
                    'expected' => $expected,
                    'gap' => round($gap, 1),
                    'significant' => $gap >= self::SIGNIFICANT_GAP_KW
                        && $gap >= $expected * self::SIGNIFICANT_GAP_RATIO,
                ];
            }
        }

        return [
            'session' => $session,
            'measured' => $measured,
            'reference' => $reference,
            'capped_at' => $cap,
            'alternating' => $alternating,
            'peak' => $measured === [] ? null : max(array_column($measured, 'y')),
            'worst' => $worst,
            // Le graphique montre tout, y compris la montee en puissance : c'est
            // le chiffre de l'ecart qui l'ignore, pas la courbe.
            'ramp_points' => count(array_filter($measured, fn ($p) => $p['ramp'])),
        ];
    }
}
