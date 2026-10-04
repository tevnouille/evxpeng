<?php

namespace App\Http\Controllers;

use App\Models\ChargingStationNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Appreciations personnelles sur les bornes (fiable, HS, acces complique).
 *
 * Affichees en lecture seule dans le planificateur et les trajets favoris
 * (App\Services\RouteCorridor) ; la saisie et la modification se font ici.
 */
class ChargingStationNoteController extends Controller
{
    public function index(): View
    {
        return view('station_notes.index', [
            'notes' => ChargingStationNote::with('station')->orderByDesc('updated_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer', 'exists:charging_stations,id'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        // updateOrCreate et non create : une seconde note sur la meme borne
        // remplace la premiere, elle ne s'empile pas (contrainte d'unicite
        // user_id + charging_station_id).
        ChargingStationNote::updateOrCreate(
            ['charging_station_id' => $data['station_id']],
            ['note' => $data['note']]
        );

        return redirect()->route('station-notes.index')->with('success', 'Note enregistrée.');
    }

    public function destroy(ChargingStationNote $stationNote): RedirectResponse
    {
        $stationNote->delete();

        return redirect()->route('station-notes.index')->with('success', 'Note supprimée.');
    }
}
