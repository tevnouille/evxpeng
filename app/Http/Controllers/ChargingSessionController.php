<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\PowerRating;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargingSessionController extends Controller
{
    public function index(): View
    {
        return view('charging_sessions.index', [
            'sessions' => ChargingSession::with(['provider', 'powerRating'])
                ->orderByDesc('session_date')
                ->orderByDesc('id')
                ->paginate(20),
            'providers' => Provider::orderBy('name')->get(),
            'powerRatings' => PowerRating::orderBy('kw')->get(),
            'editing' => null,
        ]);
    }

    public function edit(ChargingSession $chargingSession): View
    {
        return view('charging_sessions.index', [
            'sessions' => ChargingSession::with(['provider', 'powerRating'])
                ->orderByDesc('session_date')
                ->orderByDesc('id')
                ->paginate(20),
            'providers' => Provider::orderBy('name')->get(),
            'powerRatings' => PowerRating::orderBy('kw')->get(),
            'editing' => $chargingSession,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        ChargingSession::create($data);

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

    private function validated(Request $request): array
    {
        return $request->validate([
            'session_date' => ['required', 'date'],
            'provider_id' => ['required', 'exists:providers,id'],
            'power_rating_id' => ['required', 'exists:power_ratings,id'],
            'quantity_kwh' => ['required', 'numeric', 'min:0'],
            'charge_duration' => ['nullable', 'date_format:H:i'],
            'parking_duration' => ['nullable', 'date_format:H:i'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string'],
        ]);
    }
}
