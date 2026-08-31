<?php

namespace App\Services;

use App\Models\VehicleTelemetry;
use Illuminate\Support\Collection;

/**
 * Etat courant du vehicule, deduit des derniers releves.
 *
 * ABRP documente un champ `is_parked`, mais aucune de nos sources ne l'a jamais
 * renseigne. Faute de quoi que ce soit d'autre a opposer a `is_charging`, la
 * page affichait donc « stationné » y compris en plein trajet.
 *
 * Le seul signal qui dise vraiment « la voiture a bouge » est l'odometre. La
 * vitesse et la puissance sont des instantanes pris au hasard dans la fenetre
 * de mesure : on a releve 0,2 km/h et 0,3 kW sur un trajet ou le compteur
 * prenait 5 km en deux minutes. Elles ne servent donc que de secours, pour les
 * sources qui ne remontent pas d'odometre (le cloud constructeur seul).
 */
class VehicleState
{
    public const CHARGING = 'charging';

    public const DRIVING = 'driving';

    public const PARKED = 'parked';

    public const OFFLINE = 'offline';

    /**
     * Fenetre sur laquelle on cherche une progression de l'odometre.
     *
     * Deux releves consecutifs affichent souvent le meme kilometrage — arret a
     * un feu, ou simplement parce qu'on interroge toutes les 15 s. On raisonne
     * donc sur un intervalle, au prix d'un « en route » qui subsiste jusqu'a
     * dix minutes apres le stationnement.
     */
    private const MOVEMENT_WINDOW_MINUTES = 10;

    /** Vitesse au-dela de laquelle la voiture roule, en km/h. */
    private const MOVING_KMH = 3.0;

    /**
     * Age au-dela duquel le dernier releve ne dit plus rien de l'instant
     * present : une voiture a l'arret peut rester des heures sans emettre.
     */
    private const OFFLINE_MINUTES = 45;

    /** En dessous, la donnee arrive assez vite pour etre dite « en direct ». */
    private const LINK_LIVE_MINUTES = 15;

    /** Au-dela, plus rien n'explique le silence, pas meme la cadence du cloud. */
    private const LINK_SLOW_MINUTES = 90;

    /**
     * @param  Collection<int, VehicleTelemetry>  $history  Tries par recorded_at croissant.
     * @return array{state: string, label: string, tag: string, detail: string}|null
     */
    public function describe(?VehicleTelemetry $latest, Collection $history): ?array
    {
        if ($latest === null) {
            return null;
        }

        $ageMinutes = $latest->recorded_at->diffInMinutes(now());

        if ($latest->is_charging) {
            return $this->state(self::CHARGING, 'en charge', 'is-success', 'La voiture remonte une charge en cours.');
        }

        // Un releve trop vieux ne permet plus de trancher : le dire vaut mieux
        // que d'affirmer « stationné » sur une mesure d'il y a trois heures.
        if ($ageMinutes > self::OFFLINE_MINUTES) {
            return $this->state(
                self::OFFLINE,
                'sans relevé récent',
                'is-warning is-light',
                'Dernier relevé il y a '.$this->humanize($ageMinutes)
                .". La voiture n'émet plus : coupée, hors réseau, ou dongle OBD débranché."
            );
        }

        if ($this->hasMoved($history)) {
            return $this->state(self::DRIVING, 'en route', 'is-info', 'Le compteur kilométrique progresse.');
        }

        if ($this->isFast($latest->speed)) {
            return $this->state(self::DRIVING, 'en route', 'is-info', 'Vitesse remontée par la voiture.');
        }

        if ($latest->is_parked === true) {
            return $this->state(self::PARKED, 'stationné', 'is-light', 'La voiture se déclare à l\'arrêt.');
        }

        return $this->state(self::PARKED, 'stationné', 'is-light', "Ni charge ni kilomètres depuis "
            .self::MOVEMENT_WINDOW_MINUTES.' minutes.');
    }

    /**
     * L'etat sert aussi a cadencer la collecte : on interroge ABRP bien plus
     * souvent quand il se passe quelque chose.
     */
    public function isActive(?array $described): bool
    {
        return in_array($described['state'] ?? null, [self::CHARGING, self::DRIVING], true);
    }

    /**
     * Etat de la liaison, juge sur la fraicheur des releves.
     *
     * `is_connected` d'ABRP ne dit pas ce qu'on croit : il repond « une source
     * est declaree pour ce vehicule », pas « elle emet ». Il reste donc a
     * « connectée » dongle debranche, ce qui est le contraire de ce que la page
     * doit montrer. Seule la date du dernier releve repond vraiment.
     *
     * @return array{label: string, tag: string, detail: string}|null
     */
    public function link(?VehicleTelemetry $latest, Collection $history, ?bool $abrpConnected): ?array
    {
        if ($latest === null) {
            return null;
        }

        $minutes = (int) $latest->recorded_at->diffInMinutes(now());
        $declared = $abrpConnected === null
            ? "ABRP ne se prononce pas."
            : ('ABRP déclare la source '.($abrpConnected ? 'connectée' : 'déconnectée').'.');

        $via = $this->sources($latest, $history);

        // Dongle OBD actif, les points tombent toutes les quatre a cinq minutes.
        if ($minutes <= self::LINK_LIVE_MINUTES) {
            return [
                'label' => 'connectée'.$via,
                'tag' => 'is-success',
                'detail' => "Relevé il y a {$this->humanize($minutes)}. ".$declared,
            ];
        }

        // Cadence du cloud constructeur seul : environ un point par heure. La
        // voiture reste jointe, mais plus rien n'arrive en direct.
        if ($minutes <= self::LINK_SLOW_MINUTES) {
            return [
                'label' => 'au ralenti'.$via,
                'tag' => 'is-warning is-light',
                'detail' => "Rien depuis {$this->humanize($minutes)} : cadence du cloud constructeur, "
                    ."ou dongle OBD débranché. ".$declared,
            ];
        }

        return [
            'label' => 'silencieuse'.$via,
            'tag' => 'is-warning',
            'detail' => "Rien depuis {$this->humanize($minutes)}. ".$declared,
        ];
    }

    /**
     * Par ou la donnee est arrivee recemment, ex. « via obdble » ou
     * « via enode/obdble ».
     *
     * Nommer la source repond a la question que posait le seul mot
     * « connectée » : connectee a quoi ? Le cloud constructeur passe par Enode
     * et continue de repondre dongle debranche — les deux liaisons sont
     * distinctes, et peuvent coexister.
     *
     * @param  Collection<int, VehicleTelemetry>  $history
     */
    private function sources(VehicleTelemetry $latest, Collection $history): string
    {
        $recent = $history
            ->filter(fn (VehicleTelemetry $row) => $row->telemetry_type !== null
                && $row->recorded_at->greaterThanOrEqualTo(now()->subMinutes(self::LINK_SLOW_MINUTES)))
            ->pluck('telemetry_type');

        // Plus rien de recent : c'est la derniere source connue qui renseigne.
        if ($recent->isEmpty()) {
            $recent = collect([$latest->telemetry_type])->filter();
        }

        $names = $recent->unique()->sort()->values();

        return $names->isEmpty() ? '' : ' via '.$names->implode('/');
    }

    /**
     * Un vehicule au moins est-il en charge ou en roulage en ce moment ?
     *
     * Sert a cadencer la collecte : la question se pose toutes les 15 s, elle
     * reste donc une requete d'agregat et ne charge aucun releve.
     */
    public function anyActive(): bool
    {
        // Une charge reste vraie meme si le dernier point date de quelques
        // minutes : la voiture n'emet pas a chaque seconde.
        $charging = VehicleTelemetry::where('is_charging', true)
            ->where('recorded_at', '>=', now()->subMinutes(20))
            ->exists();

        if ($charging) {
            return true;
        }

        return VehicleTelemetry::whereNotNull('odometer')
            ->where('recorded_at', '>=', now()->subMinutes(self::MOVEMENT_WINDOW_MINUTES))
            ->groupBy('vehicle_id')
            ->havingRaw('MAX(odometer) > MIN(odometer)')
            ->selectRaw('vehicle_id')
            ->exists();
    }

    /**
     * L'odometre a-t-il progresse sur la fenetre glissante ?
     *
     * @param  Collection<int, VehicleTelemetry>  $history
     */
    private function hasMoved(Collection $history): bool
    {
        $window = $history
            ->filter(fn (VehicleTelemetry $row) => $row->odometer !== null
                && $row->recorded_at->greaterThanOrEqualTo(now()->subMinutes(self::MOVEMENT_WINDOW_MINUTES)))
            ->map(fn (VehicleTelemetry $row) => (float) $row->odometer);

        // Un seul point ne prouve rien : il faut deux kilometrages a comparer.
        return $window->count() >= 2 && $window->max() > $window->min();
    }

    private function isFast(mixed $speed): bool
    {
        return $speed !== null && (float) $speed >= self::MOVING_KMH;
    }

    /**
     * @return array{state: string, label: string, tag: string, detail: string}
     */
    private function state(string $state, string $label, string $tag, string $detail): array
    {
        return ['state' => $state, 'label' => $label, 'tag' => $tag, 'detail' => $detail];
    }

    private function humanize(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        return intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
