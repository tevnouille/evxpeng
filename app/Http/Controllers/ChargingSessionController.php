<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Location;
use App\Models\PowerRating;
use App\Models\Provider;
use App\Models\Vehicle;
use App\Services\PendingTelemetryCharges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingSessionController extends Controller
{
    public function index(Request $request, PendingTelemetryCharges $pending): View
    {
        $duplicateFrom = null;

        if ($request->filled('duplicate')) {
            $duplicateFrom = ChargingSession::find($request->query('duplicate'));
        }

        // Pre-remplissage depuis une recharge detectee par la telemetrie (page
        // Ma voiture). Volontairement limite a ce que la telemetrie sait : ni
        // fournisseur ni cout, qui restent a la saisie de l'utilisateur.
        $prefill = [
            'session_date' => $request->query('prefill_date'),
            'quantity_kwh' => $request->query('prefill_kwh'),
            'charge_duration' => $request->query('prefill_duration'),
            'vehicle_id' => $request->query('prefill_vehicle'),
            'telemetry_started_at' => $request->query('prefill_telemetry_start'),
            'location_id' => $request->query('prefill_location'),
            'provider_id' => $request->query('prefill_provider'),
            'power_rating_id' => $request->query('prefill_power'),
            'latitude' => $request->query('prefill_lat'),
            'longitude' => $request->query('prefill_lon'),
        ];

        return view('charging_sessions.index', [
            'sessions' => $this->recentSessions(),
            'vehicles' => Vehicle::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'providers' => Provider::orderBy('name')->get(),
            'powerRatings' => PowerRating::orderBy('kw')->get(),
            'editing' => null,
            'duplicateFrom' => $duplicateFrom,
            'prefill' => $prefill,
            'pendingCharges' => $pending->all(),
        ]);
    }

    public function edit(ChargingSession $chargingSession): View
    {
        return view('charging_sessions.index', [
            'sessions' => $this->recentSessions(),
            'vehicles' => Vehicle::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'providers' => Provider::orderBy('name')->get(),
            'powerRatings' => PowerRating::orderBy('kw')->get(),
            'editing' => $chargingSession,
            'duplicateFrom' => null,
            'prefill' => [],
            'pendingCharges' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $session = ChargingSession::create($data);

        if ($request->input('action') === 'save_and_duplicate') {
            return redirect()->route('charging-sessions.index', ['duplicate' => $session->id])
                ->with('success', 'Recharge ajoutée. Localisation, fournisseur, puissance et coût unitaire repris ci-dessous.');
        }

        return redirect()->route('charging-sessions.index')->with('success', 'Recharge ajoutée.');
    }

    public function update(Request $request, ChargingSession $chargingSession): RedirectResponse
    {
        $data = $this->validated($request);

        $chargingSession->update($data);

        return redirect()->route('charging-sessions.index')->with('success', 'Recharge mise à jour.');
    }

    public function destroy(ChargingSession $chargingSession): RedirectResponse
    {
        $chargingSession->delete();

        return redirect()->route('charging-sessions.index')->with('success', 'Recharge supprimée.');
    }

    private function recentSessions(): Collection
    {
        return ChargingSession::with(['vehicle', 'location', 'provider', 'powerRating'])
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'session_date' => ['required', 'date'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'location_choice' => ['required', 'string'],
            'location_other' => ['required_if:location_choice,other', 'nullable', 'string', 'max:255'],
            'provider_choice' => ['required', 'string'],
            'provider_other' => ['required_if:provider_choice,other', 'nullable', 'string', 'max:255'],
            'power_rating_id' => ['required', 'exists:power_ratings,id'],
            'quantity_kwh' => ['required', 'numeric', 'min:0'],
            'charge_duration' => ['nullable', 'date_format:H:i'],
            'telemetry_started_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string'],
        ]);

        $location = $this->resolveChoice(Location::class, $validated['location_choice'], $validated['location_other'] ?? null);
        $provider = $this->resolveChoice(Provider::class, $validated['provider_choice'], $validated['provider_other'] ?? null);

        unset($validated['location_choice'], $validated['location_other'], $validated['provider_choice'], $validated['provider_other']);
        $validated['location_id'] = $location->id;
        $validated['provider_id'] = $provider->id;

        return $validated;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function resolveChoice(string $modelClass, string $choice, ?string $otherValue): Model
    {
        if ($choice === 'other') {
            return $modelClass::firstOrCreate(['name' => trim($otherValue)]);
        }

        return $modelClass::findOrFail($choice);
    }
}
