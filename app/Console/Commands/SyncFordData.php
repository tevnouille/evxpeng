<?php

namespace App\Console\Commands;

use App\Models\FordTelemetry;
use App\Services\FordClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Interroge l'API FordConnect Query et enregistre un releve.
 *
 * Contrairement a xpeng:sync (export asynchrone, quota strict de 5
 * soumissions/24h), FordConnect Query repond l'etat courant a chaque appel :
 * un GET direct suffit, pas de file d'attente a interroger. Quota non publie,
 * mais bien reel : une rafale de verifications manuelles a declenche un 429
 * "Rate limit exceeded. Please wait 1 hour" le 22/09/2026. La cadence
 * planifiee (routes/console.php, un seul appel par execution) en est tres
 * loin, mais eviter les appels manuels a repetition en dehors de celle-ci.
 */
class SyncFordData extends Command
{
    protected $signature = 'ford:sync';

    protected $description = "Interroge l'API FordConnect Query et enregistre un releve.";

    public function handle(FordClient $client): int
    {
        if (! config('services.ford.client_id') || ! config('services.ford.client_secret')) {
            $this->error('Ford : client_id/client_secret non configures (voir .env), rien a faire.');

            return self::FAILURE;
        }

        if (! config('services.ford.vin')) {
            $this->error('Ford : FORD_VIN non configure (voir .env), rien a faire.');

            return self::FAILURE;
        }

        try {
            $telemetry = $client->telemetry();
        } catch (\Throwable $e) {
            $this->error("Ford : {$e->getMessage()}");
            report($e);

            return self::FAILURE;
        }

        $metriques = $telemetry['metrics'] ?? [];

        // updateTime est un horodatage UTC (suffixe Z) : le convertir avant
        // l'ecriture, sinon la colonne (sans notion de fuseau sur MariaDB) le
        // relit plus tard comme s'il etait deja en heure locale -- meme piege
        // deja rencontre pour XpengExportParser et IngestMqttTelemetry.
        $recordedAt = isset($telemetry['updateTime'])
            ? Carbon::parse($telemetry['updateTime'])->setTimezone(config('app.timezone'))
            : now();

        FordTelemetry::create([
            'vin' => $telemetry['vin'] ?? config('services.ford.vin'),
            'recorded_at' => $recordedAt,
            'soc' => $metriques['batteryStateOfCharge']['value'] ?? null,
            'odometre_km' => $metriques['odometer']['value'] ?? null,
            'battery_voltage' => $metriques['batteryVoltage']['value'] ?? null,
            'ambient_temp_c' => $metriques['ambientTemp']['value'] ?? null,
            'outside_temp_c' => $metriques['outsideTemperature']['value'] ?? null,
            'ignition_status' => $metriques['ignitionStatus']['value'] ?? null,

            // Colonnes ouvertes par le scope evData (23/09/2026, voir la
            // migration associee) : absentes tant que le jeton ne le porte
            // pas, ?? null les laisse alors simplement vides plutot que de
            // faire echouer tout l'enregistrement.
            'xev_soc' => $metriques['xevBatteryStateOfCharge']['value'] ?? null,
            'xev_range_km' => $metriques['xevBatteryRange']['value'] ?? null,
            'xev_energy_remaining_kwh' => $metriques['xevBatteryEnergyRemaining']['value'] ?? null,
            'xev_time_to_full_charge_min' => $metriques['xevBatteryTimeToFullCharge']['value'] ?? null,
            'xev_charger_energy_output_kwh' => $metriques['xevBatteryChargerEnergyOutput']['value'] ?? null,
            'xev_charger_current_output_a' => $metriques['xevBatteryChargerCurrentOutput']['value'] ?? null,
            'xev_charger_voltage_output_v' => $metriques['xevBatteryChargerVoltageOutput']['value'] ?? null,
            'plug_status' => $metriques['xevPlugChargerStatus']['value'] ?? null,
            'charge_display_status' => $metriques['xevBatteryChargeDisplayStatus']['value'] ?? null,
            'charge_station_power_type' => $metriques['xevChargeStationPowerType']['value'] ?? null,
            'speed_kmh' => $metriques['speed']['value'] ?? null,
            'latitude' => $metriques['position']['value']['location']['lat'] ?? null,
            'longitude' => $metriques['position']['value']['location']['lon'] ?? null,
            'pression_av_gauche_kpa' => $this->pressionRoue($metriques, 'FRONT_LEFT'),
            'pression_av_droite_kpa' => $this->pressionRoue($metriques, 'FRONT_RIGHT'),
            'pression_ar_gauche_kpa' => $this->pressionRoue($metriques, 'REAR_LEFT'),
            'pression_ar_droite_kpa' => $this->pressionRoue($metriques, 'REAR_RIGHT'),

            'metrics' => $metriques,
        ]);

        // Mise en cache indefinie : l'URL ne bouge pas (construite depuis le
        // VIN cote CDN Ford), un seul appel a /v1/vehicle-image pour de bon.
        // Fait ici plutot que dans FordDataController, qui ne doit jamais
        // appeler l'API depuis une requete HTTP entrante (meme principe que
        // XpengDataController). Best-effort : un echec ici (429 constate le
        // 22/09/2026, Ford applique bien une limite de debit malgre l'absence
        // de quota publie) ne doit jamais faire perdre le releve deja acquis
        // ci-dessus.
        if (! Cache::has('ford_vehicle_image_url')) {
            try {
                Cache::forever('ford_vehicle_image_url', $client->vehicleImageUrl());
            } catch (\Throwable $e) {
                $this->warn("Ford : recuperation de l'image du vehicule echouee : {$e->getMessage()}");
            }
        }

        // Meme logique que l'image ci-dessus : identite du vehicule (nom,
        // modele), qui ne change pour ainsi dire jamais -- un seul appel pour
        // de bon plutot qu'a chaque passage planifie.
        if (! Cache::has('ford_vehicle_garage')) {
            try {
                Cache::forever('ford_vehicle_garage', $client->garage());
            } catch (\Throwable $e) {
                $this->warn("Ford : recuperation de l'identite du vehicule echouee : {$e->getMessage()}");
            }
        }

        $this->info('Ford : relevé enregistré.');

        return self::SUCCESS;
    }

    /**
     * tirePressure est un tableau signale par vehicleWheel, pas un champ
     * direct -- meme forme que doorStatus/windowStatus (non exploites en
     * colonne, voir la migration).
     */
    private function pressionRoue(array $metriques, string $roue): ?float
    {
        foreach ($metriques['tirePressure'] ?? [] as $mesure) {
            if (($mesure['vehicleWheel'] ?? null) === $roue) {
                return $mesure['value'] ?? null;
            }
        }

        return null;
    }
}
