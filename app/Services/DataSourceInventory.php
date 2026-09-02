<?php

namespace App\Services;

use App\Models\ChargingStation;
use App\Models\FuelPrice;
use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use Illuminate\Support\Carbon;

/**
 * Inventaire des donnees que l'application va chercher ailleurs.
 *
 * Trois sources, trois rythmes, et surtout trois façons de vieillir : la base
 * IRVE est rechargee entierement une fois par semaine, les prix des carburants
 * une fois par jour, la telemetrie toutes les cinq minutes. Les rassembler sur
 * une page evite d'avoir a fouiller la base pour savoir si une collecte s'est
 * arretee — un import silencieusement en echec ne se voit autrement nulle part.
 *
 * L'horodatage n'est jamais declare : il est lu dans la donnee elle-meme, donc
 * il ne peut pas affirmer une fraicheur que la base n'a pas.
 */
class DataSourceInventory
{
    /**
     * Au-dela de ce retard, la source est signalee comme en retard. Genereux
     * volontairement : un import hebdomadaire n'est pas en panne parce qu'il
     * date de huit jours.
     */
    private const STALE_FACTOR = 2;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            $this->stations(),
            $this->fuelPrices(),
            $this->telemetry(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->all() as $source) {
            if ($source['key'] === $key) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function stations(): array
    {
        $count = ChargingStation::count();
        $updatedAt = ChargingStation::max('updated_at');

        return [
            'key' => 'bornes',
            'icon' => '&#9889;',
            'label' => 'Bornes de recharge',
            'description' => 'Base nationale consolidée des points de recharge (IRVE). Alimente le planificateur, les favoris et la liste des réseaux.',
            'origin' => 'data.gouv.fr',
            'volume' => $count > 0 ? number_format($count, 0, ',', ' ').' stations' : 'aucune donnée',
            'detail' => $count > 0 ? ChargingStation::networkOptions()->count().' réseaux listés' : null,
            'updated_at' => $updatedAt ? Carbon::parse($updatedAt) : null,
            'schedule' => 'chaque lundi à 4 h 30',
            'expected_hours' => 7 * 24,
            'shared' => true,
            // Le fichier fait environ 150 Mo et l'import dure plusieurs minutes :
            // impossible de le tenir dans le temps d'une requete web.
            'background' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fuelPrices(): array
    {
        $count = FuelPrice::count();
        $latest = FuelPrice::max('date');
        $updatedAt = FuelPrice::max('updated_at');

        return [
            'key' => 'carburants',
            'icon' => '&#9981;',
            'label' => 'Prix des carburants',
            'description' => "Moyennes nationales SP95 et gazole, utilisées pour comparer le coût d'une recharge à un plein.",
            'origin' => 'data.economie.gouv.fr',
            'volume' => $count > 0 ? number_format($count, 0, ',', ' ').' jours relevés' : 'aucune donnée',
            'detail' => $latest ? 'dernier jour couvert : '.Carbon::parse($latest)->format('d/m/Y') : null,
            'updated_at' => $updatedAt ? Carbon::parse($updatedAt) : null,
            'schedule' => 'au premier affichage de la journée',
            'expected_hours' => 24,
            'shared' => true,
            'background' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function telemetry(): array
    {
        // Le scope global limite deja les vehicules au compte courant : les
        // chiffres montres sont ceux de l'utilisateur, pas ceux de tout le monde.
        $vehicleIds = Vehicle::pluck('id');
        $connected = Vehicle::whereNotNull('mqtt_client_id')->count();

        $query = VehicleTelemetry::whereIn('vehicle_id', $vehicleIds);

        $count = (clone $query)->count();
        $recordedAt = (clone $query)->max('recorded_at');

        return [
            'key' => 'telemetrie',
            'icon' => '&#128663;',
            'label' => 'Télémétrie du véhicule',
            'description' => "Niveau de charge, position, compteur, santé de la batterie et températures, publiés par le boîtier OBD sur le broker MQTT de la maison.",
            'origin' => 'mqtt (broker local)',
            'volume' => $count > 0 ? number_format($count, 0, ',', ' ').' relevés' : 'aucun relevé',
            'detail' => $connected > 0
                ? $connected.' véhicule(s) relié(s) au boîtier'
                : "aucun véhicule relié : renseignez un identifiant MQTT",
            'updated_at' => $recordedAt ? Carbon::parse($recordedAt) : null,
            'schedule' => 'en continu, ingéré toutes les 15 secondes',
            'expected_hours' => 2,
            'shared' => false,
            'background' => false,
            'available' => $connected > 0,
        ];
    }

    /**
     * Une source est dite en retard quand elle a manque plusieurs fois son
     * rendez-vous : c'est le signe qu'une collecte s'est arretee.
     *
     * @param  array<string, mixed>  $source
     */
    public function isStale(array $source): bool
    {
        if ($source['updated_at'] === null) {
            return false;
        }

        return $source['updated_at']->diffInHours(now()) > $source['expected_hours'] * self::STALE_FACTOR;
    }
}
