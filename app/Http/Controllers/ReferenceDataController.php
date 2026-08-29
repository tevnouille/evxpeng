<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\PowerRating;
use App\Models\Provider;
use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    public function index(): View
    {
        return view('reference_data.index', [
            'vehiclesCount' => Vehicle::count(),
            'locationsCount' => Location::count(),
            'providersCount' => Provider::count(),
            'powerRatingsCount' => PowerRating::count(),
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
            'name' => ['required', 'string', 'max:255', 'unique:vehicles,name'],
            'charging_curve' => ['nullable', 'string', Rule::in($curves->slugs())],
            'kwh_per_100km' => ['nullable', 'numeric', 'min:0'],
            'essence_l_per_100km' => ['nullable', 'numeric', 'min:0'],
            'diesel_l_per_100km' => ['nullable', 'numeric', 'min:0'],
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
            'name' => ['required', 'string', 'max:255', 'unique:vehicles,name,' . $vehicle->id],
            'charging_curve' => ['nullable', 'string', Rule::in($curves->slugs())],
            'kwh_per_100km' => ['nullable', 'numeric', 'min:0'],
            'essence_l_per_100km' => ['nullable', 'numeric', 'min:0'],
            'diesel_l_per_100km' => ['nullable', 'numeric', 'min:0'],
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
            'locations' => Location::withCount('chargingSessions')->orderBy('name')->get(),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:locations,name']]);

        Location::create($data);

        return redirect()->route('reference-data.locations.index')->with('success', 'Localisation ajoutée.');
    }

    public function updateLocation(Request $request, Location $location): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:locations,name,' . $location->id]]);

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
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:providers,name']]);

        Provider::create($data);

        return redirect()->route('reference-data.providers.index')->with('success', 'Fournisseur ajouté.');
    }

    public function updateProvider(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:providers,name,' . $provider->id]]);

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
        $data = $request->validate(['kw' => ['required', 'numeric', 'min:0', 'unique:power_ratings,kw']]);

        PowerRating::create($data);

        return redirect()->route('reference-data.power-ratings.index')->with('success', 'Puissance ajoutée.');
    }

    public function updatePowerRating(Request $request, PowerRating $powerRating): RedirectResponse
    {
        $data = $request->validate(['kw' => ['required', 'numeric', 'min:0', 'unique:power_ratings,kw,' . $powerRating->id]]);

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
}
