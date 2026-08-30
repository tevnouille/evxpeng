<?php

namespace App\Console\Commands;

use App\Models\ChargingSession;
use App\Models\VehicleTelemetry;
use Illuminate\Console\Command;

/**
 * Renseigne la position des recharges issues de la telemetrie qui n'en ont pas.
 *
 * Aucune approximation : seules les recharges portant un telemetry_started_at
 * sont traitees, et la position vient des releves de cette charge precise.
 */
class BackfillSessionPositions extends Command
{
    protected $signature = 'telemetry:backfill-positions {--dry-run : Afficher sans enregistrer}';

    protected $description = "Complete la position des recharges creees depuis une recharge detectee";

    public function handle(): int
    {
        $sessions = ChargingSession::whereNotNull('telemetry_started_at')
            ->whereNull('latitude')
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('Rien a completer.');

            return self::SUCCESS;
        }

        $filled = 0;

        foreach ($sessions as $session) {
            // Dernier releve de la charge : la voiture y est a l'arret sur la
            // borne, donc la position est la plus representative.
            $telemetry = VehicleTelemetry::where('vehicle_id', $session->vehicle_id)
                ->where('recorded_at', '>=', $session->telemetry_started_at)
                ->whereNotNull('lat')
                ->orderBy('recorded_at')
                ->get()
                ->last();

            if ($telemetry === null) {
                $this->warn("Recharge #{$session->id} : aucun releve positionne.");

                continue;
            }

            $this->line(sprintf(
                'Recharge #%d (%s) -> %s, %s',
                $session->id,
                $session->session_date->format('d/m/Y'),
                $telemetry->lat,
                $telemetry->lon
            ));

            if (! $this->option('dry-run')) {
                $session->update(['latitude' => $telemetry->lat, 'longitude' => $telemetry->lon]);
                $filled++;
            }
        }

        $this->info($this->option('dry-run') ? 'Simulation terminee.' : "{$filled} recharge(s) completee(s).");

        return self::SUCCESS;
    }
}
