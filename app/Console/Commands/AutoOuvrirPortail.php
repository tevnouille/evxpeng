<?php

namespace App\Console\Commands;

use App\Models\MerossAction;
use App\Models\Vehicle;
use App\Services\MerossClient;
use App\Services\VehicleState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Ouvre automatiquement le portail d'entree quand la voiture s'en approche
 * (demande explicite du 30/09/2026, domicile geocode dans
 * config('services.domicile')).
 *
 * Ne touche jamais au garage : seul le portail d'entree a ete demande.
 */
class AutoOuvrirPortail extends Command
{
    protected $signature = 'portail:auto-ouverture';

    protected $description = "Ouvre le portail d'entree quand la voiture approche du domicile.";

    /** Distance sous laquelle on declenche l'ouverture, en metres. */
    private const RAYON_APPROCHE_M = 300;

    /**
     * Distance au-dela de laquelle on considere l'approche terminee et on
     * autorise un nouveau declenchement -- plus large que le rayon
     * d'approche pour ne pas ouvrir/refermer en boucle a la frontiere.
     */
    private const RAYON_RESET_M = 600;

    /**
     * Filet de securite si la voiture ne repasse jamais au-dela du rayon de
     * reinitialisation (ex : coupure GPS juste apres le declenchement) --
     * le verrou expire de lui-meme plutot que de bloquer indefiniment.
     */
    private const VERROU_MAX_HEURES = 2;

    /**
     * Releve trop vieux pour dire quoi que ce soit de la position actuelle,
     * meme seuil que $mqttPerime dans InfoCarController.
     */
    private const RELEVE_PERIME_MINUTES = 2;

    public function handle(VehicleState $state, MerossClient $meross): int
    {
        $lat = config('services.domicile.lat');
        $lon = config('services.domicile.lon');
        $uuid = config('services.meross.devices.portail');

        if (! $lat || ! $lon || ! $uuid) {
            return self::SUCCESS;
        }

        $vehicle = Vehicle::whereNotNull('mqtt_client_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $telemetry = $vehicle?->latestTelemetry;

        if (! $telemetry || ! $telemetry->lat || ! $telemetry->lon) {
            return self::SUCCESS;
        }

        if ($telemetry->recorded_at->lt(now()->subMinutes(self::RELEVE_PERIME_MINUTES))) {
            return self::SUCCESS;
        }

        $distanceM = $this->haversine($telemetry->lat, $telemetry->lon, $lat, $lon) * 1000;

        // Reinitialise le verrou des que la voiture s'est reellement eloignee
        // -- avant de regarder si elle roule : meme a l'arret hors de portee,
        // rien ne doit empecher un futur declenchement.
        if ($distanceM > self::RAYON_RESET_M) {
            Cache::forget('portail_auto_declenche');

            return self::SUCCESS;
        }

        if ($distanceM > self::RAYON_APPROCHE_M) {
            return self::SUCCESS;
        }

        // La vitesse instantanee est un signal bruyant (voir le docblock de
        // VehicleState) : on reutilise sa detection par progression
        // d'odometre plutot que de comparer $telemetry->speed a un seuil.
        $history = $vehicle->telemetries()
            ->where('recorded_at', '>=', now()->subMinutes(15))
            ->orderBy('recorded_at')
            ->get();

        $etat = $state->describe($telemetry, $history);

        if (($etat['state'] ?? null) !== VehicleState::DRIVING) {
            return self::SUCCESS;
        }

        // Un seul declenchement par approche : sans ce verrou, chaque
        // execution (toutes les 15 s tant que la voiture reste sous le
        // rayon) renverrait la meme commande d'ouverture.
        if (! Cache::add('portail_auto_declenche', true, now()->addHours(self::VERROU_MAX_HEURES))) {
            return self::SUCCESS;
        }

        $resultat = $meross->operer($uuid, 'open');

        MerossAction::create([
            'appareil' => 'portail',
            'action' => 'open',
            'source' => 'automatique',
            'reussi' => $resultat['ok'],
            'erreur' => $resultat['error'],
            'created_at' => now(),
        ]);

        if ($resultat['ok'] && $resultat['open'] !== null) {
            Cache::put('meross_etat_portail', [
                'open' => $resultat['open'],
                'at' => now()->toIso8601String(),
            ], now()->addDays(30));
        }

        if (! $resultat['ok']) {
            $this->error("Portail : ouverture automatique echouee - {$resultat['error']}");

            return self::FAILURE;
        }

        $this->info("Portail : ouverture automatique declenchee a {$distanceM}m du domicile.");

        return self::SUCCESS;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
