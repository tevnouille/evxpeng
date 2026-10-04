<?php

namespace App\Http\Controllers;

use App\Models\IgnoredTelemetryCharge;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Detections de recharge ecartees des propositions de saisie.
 *
 * Le rapprochement automatique ne reconnait qu'une recharge saisie *depuis* une
 * detection. Celle qu'on a tapee a la main reste donc proposee sans fin : ce
 * controleur permet de la ranger, sans effacer la detection elle-meme — elle
 * reste sur Ma voiture, ou le meme geste s'annule.
 */
class DetectedChargeController extends Controller
{
    public function ignore(Request $request): RedirectResponse
    {
        [$vehicle, $startedAt] = $this->resolve($request);

        IgnoredTelemetryCharge::firstOrCreate([
            'vehicle_id' => $vehicle->id,
            'started_at' => $startedAt,
        ]);

        return back()->with('success', 'Recharge détectée écartée des propositions. '
            .'Elle reste listée sur « Ma voiture », où vous pouvez la rétablir.');
    }

    public function restore(Request $request): RedirectResponse
    {
        [$vehicle, $startedAt] = $this->resolve($request);

        IgnoredTelemetryCharge::where('vehicle_id', $vehicle->id)
            ->where('started_at', $startedAt)
            ->delete();

        return back()->with('success', 'Recharge détectée rétablie : elle est de nouveau proposée à la saisie.');
    }

    /**
     * @return array{0: Vehicle, 1: string}
     */
    private function resolve(Request $request): array
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer'],
            'started_at' => ['required', 'date'],
        ]);

        // findOrFail et non un where libre : le scope global du modele limite la
        // recherche aux vehicules du compte, un identifiant force renvoie donc
        // un 404 plutot que d'agir sur la voiture de quelqu'un d'autre.
        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);

        return [$vehicle, Carbon::parse($validated['started_at'])->format('Y-m-d H:i:s')];
    }
}
