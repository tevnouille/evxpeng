<?php

namespace App\Services;

use App\Models\XpengTelemetry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Lit un export Xpeng (zip de CSV a la seconde) et l'agrege a la minute dans
 * `xpeng_telemetries`.
 *
 * Format non documente par Xpeng : trois jeux de donnees par vehicule
 * (operation, power_energy, status), chacun potentiellement scinde en
 * plusieurs fichiers ("_partN") au-dela d'un certain nombre de lignes -- pas
 * de decoupage par date, verifie sur un export reel (chevauchement du meme
 * jour entre le fichier principal et sa suite). Les trois jeux partagent le
 * meme horodatage seconde par seconde (vin + timer), mais rien ne garantit
 * cet alignement en toute circonstance : chaque fichier est donc agrege
 * independamment, jamais suppose aligne ligne a ligne avec un autre.
 *
 * Plusieurs champs bruts portent une valeur-sentinelle ("signal absent") au
 * lieu de rester vides -- 255 pour les champs codes sur un octet (vitesse,
 * SoC), des valeurs proches de 1638/1677 ou 215 pour d'autres. Reperees a
 * l'oeil sur un export reel, pas documentees : VALEURS_VALIDES ci-dessous
 * n'est donc qu'une approximation prudente, a corriger si de nouvelles
 * anomalies apparaissent sur de futurs exports.
 */
class XpengExportParser
{
    /** Plage plausible par champ ; hors plage, la valeur est ignoree. */
    private const VALEURS_VALIDES = [
        'esp_vehspd' => [0, 250],
        'ldcu_bms_soc_disp' => [0, 100],
        'ldcu_dstbatdisp_dynamic' => [0, 1000],
        'ldcu_chrgpwr' => [0, 400],
        'bms_batttempmax_gb' => [-40, 100],
        'bms_batttempmin_gb' => [-40, 100],
        'ldcu_tpmsprfl' => [100, 400],
        'ldcu_tpmsprfr' => [100, 400],
        'ldcu_tpmsprrl' => [100, 400],
        'ldcu_tpmsprrr' => [100, 400],
    ];

    /**
     * @return int Nombre de minutes (tous vehicules confondus) inserees/mises a jour.
     */
    public function importZip(string $cheminZip): int
    {
        $zip = new ZipArchive();

        if ($zip->open($cheminZip) !== true) {
            throw new \RuntimeException("Impossible d'ouvrir le zip Xpeng : $cheminZip");
        }

        // [vin][minuteEpoch] => accumulateur (voir agregateVide()).
        $agregats = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = $zip->getNameIndex($i);

            if (! str_ends_with($nom, '.csv')) {
                continue;
            }

            $this->traiterFichier($zip, $nom, $agregats);
        }

        $zip->close();

        return $this->enregistrer($agregats);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $agregats
     */
    private function traiterFichier(ZipArchive $zip, string $nom, array &$agregats): void
    {
        $flux = $zip->getStream($nom);

        if ($flux === false) {
            Log::warning("Xpeng : impossible de lire $nom dans le zip.");

            return;
        }

        // BOM UTF-8 en tete de chaque fichier Xpeng : retire sinon la
        // premiere colonne de l'en-tete ("vin") ne correspond a rien.
        $premiereLigne = true;
        $entetes = [];

        while (($ligne = fgetcsv($flux)) !== false) {
            if ($premiereLigne) {
                $premiereLigne = false;
                $ligne[0] = preg_replace('/^\xEF\xBB\xBF/', '', $ligne[0]);
                $entetes = array_flip($ligne);

                continue;
            }

            $this->accumulerLigne($ligne, $entetes, $agregats);
        }

        fclose($flux);
    }

    /**
     * @param  array<int, string>  $ligne
     * @param  array<string, int>  $entetes
     * @param  array<string, array<int, array<string, mixed>>>  $agregats
     */
    private function accumulerLigne(array $ligne, array $entetes, array &$agregats): void
    {
        $vin = $ligne[$entetes['vin']] ?? null;
        $timer = isset($entetes['timer']) ? (int) ($ligne[$entetes['timer']] ?? 0) : 0;

        if (! $vin || $timer <= 0) {
            return;
        }

        $minute = intdiv($timer, 60) * 60;
        $agregats[$vin][$minute] ??= $this->agregatVide();
        $accu = &$agregats[$vin][$minute];
        $accu['nb_releves']++;

        $vitesse = $this->valeur($ligne, $entetes, 'esp_vehspd');
        if ($vitesse !== null) {
            $accu['vitesse_somme'] += $vitesse;
            $accu['vitesse_n']++;
            $accu['vitesse_max'] = max($accu['vitesse_max'] ?? $vitesse, $vitesse);
        }

        $odometre = $this->valeur($ligne, $entetes, 'cdcu_totalodometer', filtrer: false);
        if ($odometre !== null && $timer >= ($accu['odometre_timer'] ?? 0)) {
            $accu['odometre_timer'] = $timer;
            $accu['odometre'] = $odometre;
        }

        $soc = $this->valeur($ligne, $entetes, 'ldcu_bms_soc_disp');
        if ($soc !== null) {
            $accu['soc_somme'] += $soc;
            $accu['soc_n']++;
            $accu['soc_min'] = min($accu['soc_min'] ?? $soc, $soc);
            $accu['soc_max'] = max($accu['soc_max'] ?? $soc, $soc);
        }

        $autonomie = $this->valeur($ligne, $entetes, 'ldcu_dstbatdisp_dynamic');
        if ($autonomie !== null && $timer >= ($accu['autonomie_timer'] ?? 0)) {
            $accu['autonomie_timer'] = $timer;
            $accu['autonomie'] = $autonomie;
        }

        $puissance = $this->valeur($ligne, $entetes, 'ldcu_chrgpwr');
        if ($puissance !== null) {
            $accu['puissance_somme'] += $puissance;
            $accu['puissance_n']++;
        }

        $tempMax = $this->valeur($ligne, $entetes, 'bms_batttempmax_gb');
        if ($tempMax !== null) {
            $accu['temp_max'] = max($accu['temp_max'] ?? $tempMax, $tempMax);
        }

        $tempMin = $this->valeur($ligne, $entetes, 'bms_batttempmin_gb');
        if ($tempMin !== null) {
            $accu['temp_min'] = min($accu['temp_min'] ?? $tempMin, $tempMin);
        }

        foreach ([
            'ldcu_tpmsprfl' => 'pression_av_gauche',
            'ldcu_tpmsprfr' => 'pression_av_droite',
            'ldcu_tpmsprrl' => 'pression_ar_gauche',
            'ldcu_tpmsprrr' => 'pression_ar_droite',
        ] as $champ => $cle) {
            $pression = $this->valeur($ligne, $entetes, $champ);
            if ($pression !== null && $timer >= ($accu[$cle.'_timer'] ?? 0)) {
                $accu[$cle.'_timer'] = $timer;
                $accu[$cle] = $pression;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function agregatVide(): array
    {
        return ['nb_releves' => 0, 'vitesse_somme' => 0.0, 'vitesse_n' => 0, 'soc_somme' => 0.0, 'soc_n' => 0, 'puissance_somme' => 0.0, 'puissance_n' => 0];
    }

    /**
     * @param  array<int, string>  $ligne
     * @param  array<string, int>  $entetes
     */
    private function valeur(array $ligne, array $entetes, string $champ, bool $filtrer = true): ?float
    {
        $index = $entetes[$champ] ?? null;

        if ($index === null || ! isset($ligne[$index]) || $ligne[$index] === '') {
            return null;
        }

        $v = (float) $ligne[$index];

        if (! $filtrer) {
            return $v;
        }

        $plage = self::VALEURS_VALIDES[$champ] ?? null;

        if ($plage !== null && ($v < $plage[0] || $v > $plage[1])) {
            return null;
        }

        return $v;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $agregats
     */
    private function enregistrer(array $agregats): int
    {
        $total = 0;

        foreach ($agregats as $vin => $minutes) {
            foreach ($minutes as $minuteEpoch => $accu) {
                // `timer` est un epoch Unix, donc UTC sans ambiguite -- mais
                // MariaDB n'a pas de notion de fuseau sur ses colonnes de type
                // date/heure : la valeur est relue plus tard comme si elle
                // etait deja dans le fuseau de l'appli (Europe/Paris), sans
                // conversion. Sans setTimezone() ici, l'horodatage stocke
                // reste en UTC mais s'affiche comme si il etait local, avec
                // deux heures de retard en ete -- meme piege deja documente
                // dans IngestMqttTelemetry::timestamp().
                $horodatage = CarbonImmutable::createFromTimestampUTC($minuteEpoch)
                    ->setTimezone(config('app.timezone'));

                XpengTelemetry::updateOrCreate(
                    ['vin' => $vin, 'horodatage' => $horodatage],
                    [
                        'nb_releves' => $accu['nb_releves'],
                        'vitesse_moy_kmh' => $accu['vitesse_n'] > 0 ? $accu['vitesse_somme'] / $accu['vitesse_n'] : null,
                        'vitesse_max_kmh' => $accu['vitesse_max'] ?? null,
                        'odometre_km' => $accu['odometre'] ?? null,
                        'soc_moy' => $accu['soc_n'] > 0 ? $accu['soc_somme'] / $accu['soc_n'] : null,
                        'soc_min' => $accu['soc_min'] ?? null,
                        'soc_max' => $accu['soc_max'] ?? null,
                        'autonomie_km' => $accu['autonomie'] ?? null,
                        'puissance_charge_moy_kw' => $accu['puissance_n'] > 0 ? $accu['puissance_somme'] / $accu['puissance_n'] : null,
                        'temp_batterie_max_c' => $accu['temp_max'] ?? null,
                        'temp_batterie_min_c' => $accu['temp_min'] ?? null,
                        'pression_av_gauche_kpa' => $accu['pression_av_gauche'] ?? null,
                        'pression_av_droite_kpa' => $accu['pression_av_droite'] ?? null,
                        'pression_ar_gauche_kpa' => $accu['pression_ar_gauche'] ?? null,
                        'pression_ar_droite_kpa' => $accu['pression_ar_droite'] ?? null,
                    ],
                );
                $total++;
            }
        }

        return $total;
    }
}
