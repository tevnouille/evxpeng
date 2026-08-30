<?php

namespace App\Http\Controllers;

use App\Services\ServerInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'updatePending' => $inventory->updatePending(),
            'updateLog' => $inventory->updateLog(),
            'updatableCounts' => $inventory->updatableCounts(),
        ]);
    }

    /**
     * Declenche toutes les montees compatibles d'un coup.
     *
     * Le regroupement se fait cote hote : une commande par ecosysteme, sans
     * quoi chaque dependance PHP entrainerait sa propre reconstruction.
     */
    public function updateAll(ServerInventory $inventory): RedirectResponse
    {
        if ($inventory->updatePending()) {
            return back()->with('error', 'Une mise à jour est déjà en attente : laissez-la se terminer.');
        }

        if (array_sum($inventory->updatableCounts()) === 0) {
            return back()->with('error', 'Rien à mettre à jour.');
        }

        if (! $inventory->requestUpdateAll()) {
            return back()->with('error', 'Impossible de déposer la demande de mise à jour.');
        }

        return back()->with('success', "Mise à jour groupée demandée : elle démarre dans la minute et peut prendre plusieurs minutes. Le résultat apparaîtra dans le journal ci-dessous.");
    }

    /**
     * Declenche la mise a jour d'un paquet.
     *
     * L'application ne fait que deposer la demande : l'hote la reprend, et
     * revalide de son cote que la montee est compatible. Composer est refuse
     * ici comme la-bas — la monter suppose de reconstruire l'image.
     */
    public function update(Request $request, ServerInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'ecosystem' => ['required', 'string', 'in:'.implode(',', ServerInventory::UPDATABLE)],
            'package' => ['required', 'string', 'max:200'],
        ]);

        if ($inventory->updatePending()) {
            return back()->with('error', 'Une mise à jour est déjà en attente : laissez-la se terminer.');
        }

        if (! $inventory->requestUpdate($data['ecosystem'], $data['package'])) {
            return back()->with('error', 'Impossible de déposer la demande de mise à jour.');
        }

        return back()->with('success', sprintf(
            'Mise à jour de %s demandée : elle est appliquée dans la minute, le résultat apparaîtra dans le journal ci-dessous.',
            $data['package'],
        ));
    }

    public function refresh(ServerInventory $inventory): RedirectResponse
    {
        if (! $inventory->requestRefresh()) {
            return back()->with('error', "Impossible de déposer la demande de relevé. Vérifiez les droits sur storage/app/system.");
        }

        return back()->with('success', 'Vérification demandée : le relevé est rafraîchi dans la minute qui vient, rechargez la page ensuite.');
    }
}
