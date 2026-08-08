<?php

namespace App\Http\Controllers;

use App\Models\PowerRating;
use App\Models\Provider;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    public function index(): View
    {
        return view('reference_data.index', [
            'providers' => Provider::withCount('chargingSessions')->orderBy('name')->get(),
            'powerRatings' => PowerRating::withCount('chargingSessions')->orderBy('kw')->get(),
        ]);
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:providers,name']]);

        Provider::create($data);

        return redirect()->route('reference-data.index')->with('success', 'Fournisseur ajouté.');
    }

    public function updateProvider(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:providers,name,' . $provider->id]]);

        $provider->update($data);

        return redirect()->route('reference-data.index')->with('success', 'Fournisseur mis à jour.');
    }

    public function destroyProvider(Provider $provider): RedirectResponse
    {
        try {
            $provider->delete();
        } catch (QueryException) {
            return redirect()->route('reference-data.index')
                ->with('error', "Impossible de supprimer « {$provider->name} » : utilisé par des recharges existantes.");
        }

        return redirect()->route('reference-data.index')->with('success', 'Fournisseur supprimé.');
    }

    public function storePowerRating(Request $request): RedirectResponse
    {
        $data = $request->validate(['kw' => ['required', 'numeric', 'min:0', 'unique:power_ratings,kw']]);

        PowerRating::create($data);

        return redirect()->route('reference-data.index')->with('success', 'Puissance ajoutée.');
    }

    public function updatePowerRating(Request $request, PowerRating $powerRating): RedirectResponse
    {
        $data = $request->validate(['kw' => ['required', 'numeric', 'min:0', 'unique:power_ratings,kw,' . $powerRating->id]]);

        $powerRating->update($data);

        return redirect()->route('reference-data.index')->with('success', 'Puissance mise à jour.');
    }

    public function destroyPowerRating(PowerRating $powerRating): RedirectResponse
    {
        try {
            $powerRating->delete();
        } catch (QueryException) {
            return redirect()->route('reference-data.index')
                ->with('error', "Impossible de supprimer « {$powerRating->kw} kW » : utilisée par des recharges existantes.");
        }

        return redirect()->route('reference-data.index')->with('success', 'Puissance supprimée.');
    }
}
