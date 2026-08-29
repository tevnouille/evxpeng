<?php

namespace App\Services;

use App\Models\ChargingSession;
use App\Models\Vehicle;

/**
 * Recharges reperees par la telemetrie et pas encore saisies.
 *
 * Le rapprochement se fait sur l'horodatage de debut : une recharge saisie
 * depuis une detection porte ce marqueur (charging_sessions.telemetry_started_at)
 * et n'est donc plus proposee. Les saisies manuelles, elles, ne portent aucun
 * marqueur et n'empechent rien — deux recharges au meme instant restent un cas
 * que l'utilisateur tranche lui-meme.
 */
class PendingTelemetryCharges
{
    private const DEFAULT_DAYS = 30;

    public function __construct(
        private readonly TelemetrySessionDetector $detector,
        private readonly ChargingCurveRepository $curves,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>  Les plus recentes d'abord.
     */
    public function all(int $days = self::DEFAULT_DAYS): array
    {
        $recorded = array_flip(
            ChargingSession::whereNotNull('telemetry_started_at')
                ->pluck('telemetry_started_at')
                ->map(fn ($date) => $date->format('Y-m-d H:i:s'))
                ->all()
        );

        $pending = [];

        $vehicles = Vehicle::whereNotNull('abrp_token')->orderBy('name')->get();

        foreach ($vehicles as $vehicle) {
            $curve = $vehicle->charging_curve ? $this->curves->find($vehicle->charging_curve) : null;

            $rows = $vehicle->telemetries()
                ->where('recorded_at', '>=', now()->subDays($days))
                ->orderBy('recorded_at')
                ->get();

            foreach ($this->detector->detect($rows, $curve['battery_net_kwh'] ?? null) as $session) {
                // Une charge en cours n'a pas encore de duree ni d'energie finales.
                if ($session['in_progress']) {
                    continue;
                }

                if (isset($recorded[$session['started_at']->format('Y-m-d H:i:s')])) {
                    continue;
                }

                $session['vehicle'] = $vehicle;
                $pending[] = $session;
            }
        }

        usort($pending, fn ($a, $b) => $b['started_at'] <=> $a['started_at']);

        return $pending;
    }
}
