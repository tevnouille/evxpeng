<?php

namespace App\Console\Commands;

use App\Models\FordTelemetry;
use App\Services\FordClient;
use Illuminate\Console\Command;

/**
 * Interroge l'API FordConnect Query et enregistre un releve.
 *
 * Contrairement a xpeng:sync (export asynchrone, quota strict de 5
 * soumissions/24h), FordConnect Query repond l'etat courant a chaque appel :
 * un GET direct suffit, pas de file d'attente a interroger. Aucun quota
 * publie pour ce point d'entree ; la cadence planifiee (routes/console.php)
 * reste prudente en attendant d'en savoir plus en conditions reelles.
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

        FordTelemetry::create([
            'vin' => $telemetry['vin'] ?? config('services.ford.vin'),
            'recorded_at' => $telemetry['updateTime'] ?? now(),
            'soc' => $metriques['batteryStateOfCharge']['value'] ?? null,
            'odometre_km' => $metriques['odometer']['value'] ?? null,
            'battery_voltage' => $metriques['batteryVoltage']['value'] ?? null,
            'ambient_temp_c' => $metriques['ambientTemp']['value'] ?? null,
            'outside_temp_c' => $metriques['outsideTemperature']['value'] ?? null,
            'ignition_status' => $metriques['ignitionStatus']['value'] ?? null,
            'metrics' => $metriques,
        ]);

        $this->info('Ford : relevé enregistré.');

        return self::SUCCESS;
    }
}
