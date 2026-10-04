<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\PowerRating;
use App\Models\Provider;
use App\Models\SmsMessage;
use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use App\Services\DataSourceInventory;
use App\Services\FreeMobileSms;
use App\Support\CurrentUser;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    /**
     * Regles communes aux deux formulaires (ajout et modification) pour les
     * champs de la carte « Amortissement » de Ma voiture. Repetees ici plutot
     * qu'extraites en Form Request : le reste du controleur ne suit pas ce
     * decoupage, introduire une seule classe pour ces huit champs aurait
     * casse la coherence sans que rien d'autre ne le justifie.
     */
    private const PAYBACK_RULES = [
        'purchase_price' => ['nullable', 'numeric', 'min:0'],
        'purchase_date' => ['nullable', 'date'],
        'purchase_odometer_km' => ['nullable', 'integer', 'min:0'],
        'thermal_equivalent_label' => ['nullable', 'string', 'max:255'],
        'thermal_equivalent_price' => ['nullable', 'numeric', 'min:0'],
        'ev_maintenance_cost' => ['nullable', 'numeric', 'min:0'],
        'ev_maintenance_interval_km' => ['nullable', 'integer', 'min:1'],
        'thermal_maintenance_cost' => ['nullable', 'numeric', 'min:0'],
        'thermal_maintenance_interval_km' => ['nullable', 'integer', 'min:1'],
    ];

    public function index(DataSourceInventory $inventory): View
    {
        $sources = $inventory->all();

        return view('reference_data.index', [
            'vehiclesCount' => Vehicle::count(),
            'locationsCount' => Location::count(),
            'providersCount' => Provider::count(),
            'powerRatingsCount' => PowerRating::count(),
            'smsCount' => SmsMessage::count(),
            'smsFailedCount' => SmsMessage::where('delivered', false)->count(),
            'dataSourceCount' => count($sources),
            // Une collecte arretee ne se signale pas d'elle-meme : la remonter
            // des l'accueil evite de decouvrir des semaines plus tard que les
            // bornes datent d'un mois.
            'staleSourceCount' => count(array_filter($sources, fn ($source) => $inventory->isStale($source))),
        ]);
    }

    public function smsMessages(FreeMobileSms $sms): View
    {
        return view('reference_data.sms', [
            'messages' => SmsMessage::latest('created_at')->paginate(50),
            'deliveredCount' => SmsMessage::where('delivered', true)->count(),
            'failedCount' => SmsMessage::where('delivered', false)->count(),
            // Les identifiants appartiennent au compte connecte, pas a l'application.
            'configured' => $sms->forUser(CurrentUser::get())->configured(),
            'thresholds' => config('services.charge_alerts.thresholds', []),
        ]);
    }

    public function vehicles(ChargingCurveRepository $curves): View
    {
        return view('reference_data.vehicles', [
            'vehicles' => Vehicle::withCount('chargingSessions')->orderBy('name')->get(),
            'curves' => $curves->all(),
        ]);
    }

    public function storeVehicle(Request $request, ChargingCurveRepository $curves): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueForUser('vehicles', 'name')],
            'charging_curve' => ['nullable', 'string', Rule::in($curves->slugs())],
            'mqtt_client_id' => ['nullable', 'string', 'max:255'],
            'kwh_per_100km' => ['nullable', 'numeric', 'min:0'],
            'essence_l_per_100km' => ['nullable', 'numeric', 'min:0'],
            'diesel_l_per_100km' => ['nullable', 'numeric', 'min:0'],
            ...self::PAYBACK_RULES,
        ]);

        $vehicle = Vehicle::create($data);

        if ($request->boolean('is_default') || Vehicle::count() === 1) {
            $vehicle->makeDefault();
        }

        return redirect()->route('reference-data.vehicles.index')->with('success', 'Véhicule ajouté.');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle, ChargingCurveRepository $curves): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueForUser('vehicles', 'name', $vehicle->id)],
            'charging_curve' => ['nullable', 'string', Rule::in($curves->slugs())],
            'mqtt_client_id' => ['nullable', 'string', 'max:255'],
            'kwh_per_100km' => ['nullable', 'numeric', 'min:0'],
            'essence_l_per_100km' => ['nullable', 'numeric', 'min:0'],
            'diesel_l_per_100km' => ['nullable', 'numeric', 'min:0'],
            ...self::PAYBACK_RULES,
        ]);

        $vehicle->update($data);

        if ($request->boolean('is_default')) {
            $vehicle->makeDefault();
        } else {
            $vehicle->update(['is_default' => false]);
        }

        return redirect()->route('reference-data.vehicles.index')->with('success', 'Véhicule mis à jour.');
    }

    public function destroyVehicle(Vehicle $vehicle): RedirectResponse
    {
        try {
            $vehicle->delete();
        } catch (QueryException) {
            return redirect()->route('reference-data.vehicles.index')
                ->with('error', "Impossible de supprimer « {$vehicle->name} » : utilisé par des recharges existantes.");
        }

        return redirect()->route('reference-data.vehicles.index')->with('success', 'Véhicule supprimé.');
    }

    public function locations(): View
    {
        return view('reference_data.locations', [
            'locations' => Location::withCount([
                'chargingSessions',
                // Recharges dont on connait la position exacte : ce sont elles qui
                // permettent de distinguer plusieurs bornes d'une meme ville.
                'chargingSessions as located_sessions_count' => fn ($query) => $query->whereNotNull('latitude'),
            ])->orderBy('name')->get(),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueForUser('locations', 'name')],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        Location::create($data);

        return redirect()->route('reference-data.locations.index')->with('success', 'Localisation ajoutée.');
    }

    public function updateLocation(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueForUser('locations', 'name', $location->id)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $location->update($data);

        return redirect()->route('reference-data.locations.index')->with('success', 'Localisation mise à jour.');
    }

    public function destroyLocation(Location $location): RedirectResponse
    {
        try {
            $location->delete();
        } catch (QueryException) {
            return redirect()->route('reference-data.locations.index')
                ->with('error', "Impossible de supprimer « {$location->name} » : utilisée par des recharges existantes.");
        }

        return redirect()->route('reference-data.locations.index')->with('success', 'Localisation supprimée.');
    }

    public function providers(): View
    {
        return view('reference_data.providers', [
            'providers' => Provider::withCount('chargingSessions')->orderBy('name')->get(),
        ]);
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', $this->uniqueForUser('providers', 'name')]]);

        Provider::create($data);

        return redirect()->route('reference-data.providers.index')->with('success', 'Fournisseur ajouté.');
    }

    public function updateProvider(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', $this->uniqueForUser('providers', 'name', $provider->id)]]);

        $provider->update($data);

        return redirect()->route('reference-data.providers.index')->with('success', 'Fournisseur mis à jour.');
    }

    public function destroyProvider(Provider $provider): RedirectResponse
    {
        try {
            $provider->delete();
        } catch (QueryException) {
            return redirect()->route('reference-data.providers.index')
                ->with('error', "Impossible de supprimer « {$provider->name} » : utilisé par des recharges existantes.");
        }

        return redirect()->route('reference-data.providers.index')->with('success', 'Fournisseur supprimé.');
    }

    public function powerRatings(): View
    {
        return view('reference_data.power_ratings', [
            'powerRatings' => PowerRating::withCount('chargingSessions')->orderBy('kw')->get(),
        ]);
    }

    public function storePowerRating(Request $request): RedirectResponse
    {
        $data = $request->validate(['kw' => ['required', 'numeric', 'min:0', $this->uniqueForUser('power_ratings', 'kw')]]);

        PowerRating::create($data);

        return redirect()->route('reference-data.power-ratings.index')->with('success', 'Puissance ajoutée.');
    }

    public function updatePowerRating(Request $request, PowerRating $powerRating): RedirectResponse
    {
        $data = $request->validate(['kw' => ['required', 'numeric', 'min:0', $this->uniqueForUser('power_ratings', 'kw', $powerRating->id)]]);

        $powerRating->update($data);

        return redirect()->route('reference-data.power-ratings.index')->with('success', 'Puissance mise à jour.');
    }

    public function destroyPowerRating(PowerRating $powerRating): RedirectResponse
    {
        try {
            $powerRating->delete();
        } catch (QueryException) {
            return redirect()->route('reference-data.power-ratings.index')
                ->with('error', "Impossible de supprimer « {$powerRating->kw} kW » : utilisée par des recharges existantes.");
        }

        return redirect()->route('reference-data.power-ratings.index')->with('success', 'Puissance supprimée.');
    }

    /**
     * Unicite d'un libelle a l'interieur du compte, et non dans toute la base :
     * deux utilisateurs ont le droit d'avoir chacun leur "Maison".
     */
    private function uniqueForUser(string $table, string $column, ?int $ignore = null): Unique
    {
        $rule = Rule::unique($table, $column)->where('user_id', CurrentUser::id());

        return $ignore === null ? $rule : $rule->ignore($ignore);
    }
}
