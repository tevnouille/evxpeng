<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use Illuminate\Support\Carbon;
use App\Services\DataSourceInventory;
use App\Services\FuelPriceService;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

/**
 * Etat des donnees rapatriees depuis l'exterieur, et relance manuelle.
 *
 * Les sources partagees (bornes, carburants) sont communes a tous les comptes :
 * seul l'administrateur peut les relancer. La telemetrie ne concerne que les
 * vehicules du compte courant, chacun peut donc la rafraichir.
 */
class DataSourceController extends Controller
{
    public function __construct(
        private readonly DataSourceInventory $inventory,
        private readonly FuelPriceService $fuelPrices,
    ) {
    }

    public function index(): View
    {
        return view('reference_data.data_sources', [
            'sources' => $this->inventory->all(),
            'inventory' => $this->inventory,
            'isAdmin' => (bool) CurrentUser::get()?->is_admin,
        ]);
    }

    public function refresh(string $source): RedirectResponse
    {
        $definition = $this->inventory->find($source);

        abort_if($definition === null, 404);

        // Relancer une source partagee touche les donnees de tout le monde.
        abort_if($definition['shared'] && ! CurrentUser::get()?->is_admin, 403);

        return match ($source) {
            'bornes' => $this->refreshStations(),
            'carburants' => $this->refreshFuelPrices(),
            'telemetrie' => $this->refreshTelemetry(),
        };
    }

    private function refreshStations(): RedirectResponse
    {
        // Le fichier fait environ 150 Mo : impossible de tenir dans une requete
        // web. Le `&` du shell detache reellement le processus — un
        // Process::start() serait tue a la destruction de l'objet, donc a la fin
        // de la requete.
        $log = storage_path('logs/irve-import.log');

        $process = Process::fromShellCommandline(
            sprintf('nohup php artisan irve:import >> %s 2>&1 &', escapeshellarg($log)),
            base_path()
        );

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('Import IRVE : lancement impossible', ['message' => $e->getMessage()]);

            return back()->with('error', "L'import n'a pas pu être lancé : ".$e->getMessage());
        }

        return back()->with('success', "Import des bornes lancé en arrière-plan. Il dure plusieurs minutes ; l'horodatage se mettra à jour une fois terminé.");
    }

    private function refreshFuelPrices(): RedirectResponse
    {
        $updated = $this->fuelPrices->refreshToday();

        return $updated
            ? back()->with('success', 'Prix des carburants remis à jour.')
            : back()->with('error', "Le service des prix n'a rien renvoyé : réessayez plus tard.");
    }

    private function refreshTelemetry(): RedirectResponse
    {
        $vehicles = Vehicle::whereNotNull('mqtt_client_id')->get();

        if ($vehicles->isEmpty()) {
            return back()->with('error', "Aucun véhicule relié au boîtier : renseignez un identifiant MQTT sur la fiche du véhicule.");
        }

        $before = VehicleTelemetry::whereIn('vehicle_id', $vehicles->pluck('id'))->max('recorded_at');

        // Plus d'appel a un service distant : le boitier pousse en continu vers
        // le broker, et la commande ne fait que vider le tampon. Le bouton sert
        // donc a ne pas attendre le prochain passage du planificateur.
        Artisan::call('telemetry:ingest-mqtt');

        $after = VehicleTelemetry::whereIn('vehicle_id', $vehicles->pluck('id'))->max('recorded_at');

        // Dire si l'appel a rapporte quelque chose : sans cela, le bouton donne
        // le meme message qu'il ait ramene un point ou rien du tout.
        if ($after === $before) {
            return back()->with('success', "Rien de nouveau : le boîtier n'a rien publié depuis le dernier relevé. Il n'émet que téléphone présent dans la voiture.");
        }

        return back()->with('success', 'Nouveau relevé récupéré : '
            .Carbon::parse($after)->timezone(config('app.timezone'))->format('d/m/Y H:i').'.');
    }
}
