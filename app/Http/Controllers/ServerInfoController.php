<?php

namespace App\Http\Controllers;

use App\Services\ServerInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Etat du serveur et des dependances.
 *
 * Reservee a l'administrateur par la route : ce que la page expose (versions
 * exactes, paquets en retard) est precisement ce qu'on ne montre pas au tout
 * venant.
 */
class ServerInfoController extends Controller
{
    public function index(ServerInventory $inventory): View
    {
        return view('reference_data.server_info', [
            'available' => $inventory->exists(),
            'generatedAt' => $inventory->generatedAt(),
            'stale' => $inventory->isStale(),
            'pending' => $inventory->refreshPending(),
            'host' => $inventory->host(),
            'runtimes' => $inventory->runtimes(),
            'containers' => $inventory->containers(),
            'groups' => $inventory->groups(),
            'counts' => $inventory->counts(),
            'actionable' => $inventory->actionable(),
        ]);
    }

    public function refresh(ServerInventory $inventory): RedirectResponse
    {
        if (! $inventory->requestRefresh()) {
            return back()->with('error', "Impossible de déposer la demande de relevé. Vérifiez les droits sur storage/app/system.");
        }

        return back()->with('success', 'Vérification demandée : le relevé est rafraîchi dans la minute qui vient, rechargez la page ensuite.');
    }
}
