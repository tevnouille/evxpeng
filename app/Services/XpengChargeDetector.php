<?php

namespace App\Services;

use App\Models\XpengTelemetry;
use Illuminate\Support\Collection;

/**
 * Detecte les recharges dans les releves Xpeng (xpeng_telemetries), a defaut
 * d'un signal de charge direct et fiable dans l'export constructeur.
 *
 * Regle (demandee par l'utilisateur) : vitesse a l'arret (0 km/h) et, sur
 * cette meme minute, puissance de charge positive et/ou SoC en hausse par
 * rapport a la minute precedente -- l'un ou l'autre, pas forcement les deux,
 * puisque le SoC met parfois du temps a bouger en debut de charge lente.
 *
 * Le SoC seul est un signal bruyant (une variation infime peut se lire comme
 * une hausse d'une minute a l'autre sans que la voiture charge reellement) :
 * en pratique ce sont les minutes a puissance > 0 qui portent l'essentiel de
 * la detection, le SoC ne complete que les rares minutes sans lecture de
 * puissance exploitable.
 */
class XpengChargeDetector
{
    /**
     * Au-dela, deux episodes sont consideres distincts plutot qu'une seule
     * recharge interrompue -- les releves Xpeng ont des trous (voiture
     * endormie), une charge reelle ne s'interrompt pas dix minutes sans
     * qu'aucune minute intermediaire ne remonte rien.
     */
    private const TOLERANCE_MINUTES = 10;

    /**
     * @param  Collection<int, XpengTelemetry>  $releves  Deja tries par horodatage croissant.
     * @return array<int, array{debut: \Illuminate\Support\Carbon, fin: \Illuminate\Support\Carbon, duree_minutes: int, soc_debut: ?float, soc_fin: ?float, puissance_max_kw: float, energie_kwh: float}>
     */
    public function detecter(Collection $releves): array
    {
        $socPrecedent = null;
        $sessions = [];
        $courante = null;

        foreach ($releves as $releve) {
            $socMonte = $socPrecedent !== null
                && $releve->soc_moy !== null
                && $releve->soc_moy > $socPrecedent;

            $enCharge = $releve->vitesse_max_kmh === 0.0
                && ($releve->puissance_charge_moy_kw > 0 || $socMonte);

            if ($releve->soc_moy !== null) {
                $socPrecedent = $releve->soc_moy;
            }

            if (! $enCharge) {
                continue;
            }

            if ($courante !== null && abs($releve->horodatage->diffInMinutes($courante['fin'])) > self::TOLERANCE_MINUTES) {
                $sessions[] = $this->finaliser($courante);
                $courante = null;
            }

            $courante ??= [
                'debut' => $releve->horodatage,
                'fin' => $releve->horodatage,
                'soc_debut' => $releve->soc_moy,
                'soc_fin' => $releve->soc_moy,
                'puissance_max_kw' => 0.0,
                'energie_kwh' => 0.0,
            ];

            $courante['fin'] = $releve->horodatage;
            $courante['soc_fin'] = $releve->soc_moy ?? $courante['soc_fin'];
            $courante['puissance_max_kw'] = max($courante['puissance_max_kw'], $releve->puissance_charge_moy_kw ?? 0);
            // Integrale grossiere : puissance moyenne de la minute x 1/60 h.
            // La table est agregee a la minute (voir XpengExportParser), pas
            // moyen de faire mieux sans redescendre au brut a la seconde.
            $courante['energie_kwh'] += ($releve->puissance_charge_moy_kw ?? 0) / 60;
        }

        if ($courante !== null) {
            $sessions[] = $this->finaliser($courante);
        }

        return array_reverse($sessions);
    }

    /**
     * @param  array<string, mixed>  $courante
     * @return array<string, mixed>
     */
    private function finaliser(array $courante): array
    {
        $courante['duree_minutes'] = abs($courante['debut']->diffInMinutes($courante['fin'])) + 1;

        return $courante;
    }
}
