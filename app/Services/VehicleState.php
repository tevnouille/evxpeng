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

    /**
     * Cadence de rafraichissement de la page « Ma voiture », en secondes.
     *
     * On regarde la page pour des raisons differentes selon l'etat : suivre une
     * charge se fait au rythme ou la puissance evolue, un trajet au rythme ou
     * les kilometres tombent, et une voiture a l'arret n'a rien a raconter.
     * Recharger plus vite que la collecte n'apporterait rien : la cadence de
     * `telemetry:ingest-mqtt` suit la meme regle.
     *
     * **Dix secondes, parce que c'est la cadence mesuree du boitier** (mediane
     * 10,0 s sur 5 527 messages du 1er au 8 septembre 2026, identique en charge
     * et en roulage). Les cinq secondes de la charge faisaient une requete sur
     * deux pour rien, et les vingt secondes du roulage affichaient une donnee
     * deux fois plus vieille que necessaire.
     */
    public const REFRESH_SECONDS = [
        self::CHARGING => 10,
        self::DRIVING => 10,
        self::PARKED => 60,
        self::OFFLINE => 60,
    ];

    /** Vitesse au-dela de laquelle la voiture roule, en km/h. */
    private const MOVING_KMH = 3.0;

    /**
     * Age au-dela duquel le dernier releve ne dit plus rien de l'instant
     * present : une voiture a l'arret peut rester des heures sans emettre.
     */
    private const OFFLINE_MINUTES = 45;

    /** Duree pendant laquelle une charge reste reputee en cours. */
    private const CHARGING_WINDOW_MINUTES = 20;

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

        // La vitesse ne sert que faute d'odometre, et jamais sur un releve
        // vieilli. ABRP reemet la derniere mesure connue sous un horodatage
        // neuf : on a vu 24,6 km/h rejoues trois heures apres l'arret, avec le
        // meme kilometrage. Des lors que le compteur repond, il tranche seul —
        // s'en remettre a la vitesse revenait a laisser un instantane perime
        // contredire la seule mesure qui prouve un deplacement.
        if (! $this->hasOdometer($history)
            && $ageMinutes <= self::MOVEMENT_WINDOW_MINUTES
            && $this->isFast($latest->speed)) {
            return $this->state(self::DRIVING, 'en route', 'is-info', 'Vitesse remontée par la voiture, faute de compteur kilométrique.');
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
     * Aucun drapeau ne repond a cette question. Celui d'ABRP, `is_connected`,
     * disait « une source est declaree pour ce vehicule », pas « elle emet » :
     * il restait a « connectée » dongle debranche. Seule la date du dernier
     * releve repond vraiment, d'ou ce jugement sur la seule fraicheur.
     *
     * @return array{label: string, tag: string, detail: string}|null
     */
    public function link(?VehicleTelemetry $latest, Collection $history): ?array
    {
        if ($latest === null) {
            return null;
        }

        $minutes = (int) $latest->recorded_at->diffInMinutes(now());

        // La source n'est nommee dans le libelle que si la donnee est fraiche :
        // « via obdble » se lit au present, et un releve d'il y a une demi-heure
        // ne prouve pas qu'un dongle est branche maintenant. Passe ce delai, la
        // source repasse au petit texte, au passe.
        // e() : `detail` est rendu sans echappement pour porter la balise, et
        // telemetry_type vient d'ABRP — c'est de la donnee externe.
        $last = $latest->telemetry_type;
        $seen = $last === null ? '' : ' Dernier relevé via <code>'.e($last).'</code>.';

        // Dongle OBD actif, les points tombent toutes les quatre a cinq minutes.
        if ($minutes <= self::LINK_LIVE_MINUTES) {
            return [
                'label' => 'connectée'.$this->sources($latest, $history),
                'tag' => 'is-success',
                'detail' => "Relevé il y a {$this->humanize($minutes)}.",
            ];
        }

        // Le boitier n'emet que telephone present dans la voiture : un silence
        // de quelques dizaines de minutes est banal, il ne signale pas une panne.
        if ($minutes <= self::LINK_SLOW_MINUTES) {
            return [
                'label' => 'au ralenti',
                'tag' => 'is-warning is-light',
                'detail' => "Rien depuis {$this->humanize($minutes)} : téléphone absent de la voiture, "
                    ."XPCarData arrêté, ou dongle débranché.".$seen,
            ];
        }

        return [
            'label' => 'silencieuse',
            'tag' => 'is-warning',
            'detail' => "Rien depuis {$this->humanize($minutes)}.".$seen,
        ];
    }

    /**
     * Par ou la donnee arrive *en ce moment*, ex. « via obdble » ou
     * « via enode/obdble ».
     *
     * Nommer la source repond a la question que posait le seul mot
     * « connectée » : connectee a quoi ? Le cloud constructeur passe par Enode
     * et continue de repondre dongle debranche — les deux liaisons sont
     * distinctes, et peuvent coexister. D'ou la fenetre courte : au-dela, on ne
     * sait plus qui emet, seulement qui emettait.
     *
     * @param  Collection<int, VehicleTelemetry>  $history
     */
    private function sources(VehicleTelemetry $latest, Collection $history): string
    {
        $names = $history
            ->filter(fn (VehicleTelemetry $row) => $row->telemetry_type !== null
                && $row->recorded_at->greaterThanOrEqualTo(now()->subMinutes(self::LINK_LIVE_MINUTES)))
            ->pluck('telemetry_type')
            ->unique()
            ->sort()
            ->values();

        return $names->isEmpty() ? '' : ' via '.$names->implode('/');
    }

    /**
     * Un vehicule au moins est-il en charge ou en roulage en ce moment ?
     *
     * Sert a cadencer la collecte : la question se pose toutes les 15 s, elle
     * reste donc une requete d'agregat et ne charge aucun releve.
     */
    public function anyCharging(): bool
    {
        return VehicleTelemetry::where('is_charging', true)
            ->where('recorded_at', '>=', now()->subMinutes(self::CHARGING_WINDOW_MINUTES))
            ->exists();
    }

    public function anyActive(): bool
    {
        // Une charge reste vraie meme si le dernier point date de quelques
        // minutes : la voiture n'emet pas a chaque seconde.
        if ($this->anyCharging()) {
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

    /**
     * La source fournit-elle un odometre sur la periode observee ?
     *
     * @param  Collection<int, VehicleTelemetry>  $history
     */
    private function hasOdometer(Collection $history): bool
    {
        return $history->contains(fn (VehicleTelemetry $row) => $row->odometer !== null);
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
