<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use App\Services\AbrpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PollAbrpTelemetry extends Command
{
    protected $signature = 'telemetry:poll {--vehicle= : Ne traiter que ce vehicule (id)}';

    protected $description = "Recupere aupres d'ABRP la derniere telemetrie de chaque vehicule dont le token est renseigne";

    public function handle(AbrpClient $abrp): int
    {
        if (! $abrp->configured()) {
            $this->error('ABRP_API_KEY est absent du .env : rien a faire.');

            return self::FAILURE;
        }

        $vehicles = Vehicle::whereNotNull('abrp_token')
            ->when($this->option('vehicle'), fn ($query, $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        if ($vehicles->isEmpty()) {
            $this->warn('Aucun vehicule ne porte de token ABRP.');

            return self::SUCCESS;
        }

        $stored = 0;

        foreach ($vehicles as $vehicle) {
            $result = $abrp->latestTelemetry($vehicle->abrp_token);

            if ($result === null) {
                $this->warn("{$vehicle->name} : aucune telemetrie disponible.");

                continue;
            }

            $telemetry = $result['telemetry'];
            $recordedAt = Carbon::parse($result['timestamp']);

            // La meme mesure est renvoyee tant que la voiture n'a pas remonte de
            // nouveau point : on ecrase la ligne de meme horodatage plutot que
            // d'empiler des doublons.
            $row = VehicleTelemetry::updateOrCreate(
                ['vehicle_id' => $vehicle->id, 'recorded_at' => $recordedAt],
                [
                    'soc' => $telemetry['soc'] ?? null,
                    'is_charging' => (bool) ($telemetry['is_charging'] ?? false),
                    'is_connected' => (bool) ($result['is_connected'] ?? false),
                    'lat' => $telemetry['lat'] ?? null,
                    'lon' => $telemetry['lon'] ?? null,
                    'telemetry_type' => $result['telemetry_type'] ?? null,
                    // Champs presents uniquement quand la source les pousse : le
                    // cloud constructeur seul ne fournit ni odometre ni SoH.
                    'odometer' => $telemetry['odometer'] ?? null,
                    'soh' => $telemetry['soh'] ?? null,
                    'power_kw' => $telemetry['power'] ?? null,
                    'batt_temp' => $telemetry['batt_temp'] ?? null,
                    'ext_temp' => $telemetry['ext_temp'] ?? null,
                    'speed' => $telemetry['speed'] ?? null,
                    'is_dcfc' => isset($telemetry['is_dcfc']) ? (bool) $telemetry['is_dcfc'] : null,
                    'is_parked' => isset($telemetry['is_parked']) ? (bool) $telemetry['is_parked'] : null,
                    'raw' => $result,
                ]
            );

            if ($row->wasRecentlyCreated) {
                $stored++;
            }

            $this->line(sprintf(
                '%s : %s%% %s (%s)%s',
                $vehicle->name,
                $telemetry['soc'] ?? '?',
                ($telemetry['is_charging'] ?? false) ? 'en charge' : 'a l\'arret',
                $recordedAt->diffForHumans(),
                $row->wasRecentlyCreated ? '' : ' — deja connu'
            ));
        }

        $this->info("{$stored} nouvelle(s) mesure(s) enregistree(s).");

        return self::SUCCESS;
    }
}
