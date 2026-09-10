<?php

namespace App\Http\Controllers;

use App\Models\PositionShare;
use App\Models\Vehicle;
use App\Services\FreeMobileSms;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Partage temporaire de la position du vehicule, par lien unique.
 *
 * Le lien part par SMS sur le telephone du compte connecte : l'API Free
 * Mobile n'accepte aucun parametre de destination, elle ne notifie que le
 * proprietaire des identifiants (cf. App\Services\FreeMobileSms). A charge
 * pour l'utilisateur de retransmettre le lien lui-meme.
 */
class PositionShareController extends Controller
{
    /**
     * Durees proposees, en heures : un menu plutot qu'un champ date/heure —
     * plus rapide a remplir. La vue affiche 168 en jours, pas en heures.
     */
    private const DURATIONS = [1, 8, 12, 24, 24 * 7];

    public function __construct(private readonly FreeMobileSms $sms)
    {
    }

    public function index(): View
    {
        return view('position_shares.index', [
            'shares' => PositionShare::with('vehicle')->orderByDesc('created_at')->get(),
            'smsConfigured' => $this->sms->forUser(CurrentUser::get())->configured(),
            'durations' => self::DURATIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'duree' => ['required', 'integer', 'in:'.implode(',', self::DURATIONS)],
        ]);

        $expiresAt = now()->addHours((int) $data['duree']);

        // Vehicule par defaut relie au boitier : aucun choix propose tant
        // qu'un seul compte n'a jamais plus d'un vehicule equipe.
        $vehicle = Vehicle::whereNotNull('mqtt_client_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($vehicle === null) {
            return back()->with('error', "Aucun véhicule relié au boîtier : rien à partager.");
        }

        $share = PositionShare::create([
            'vehicle_id' => $vehicle->id,
            'expires_at' => $expiresAt,
        ]);

        $message = sprintf(
            'Position de %s partagée jusqu\'au %s : %s',
            $vehicle->name,
            $share->expires_at->format('d/m H:i'),
            $share->url()
        );

        if ($this->sms->forUser(CurrentUser::get())->send($message)) {
            return redirect()->route('position-shares.index')
                ->with('success', 'Lien envoyé par SMS sur votre téléphone.');
        }

        return redirect()->route('position-shares.index')->with('error',
            'Envoi SMS impossible (identifiants Free Mobile absents ou refusés dans /mon-compte). '
            .'Lien créé, à transmettre vous-même : '.$share->url());
    }

    public function destroy(PositionShare $positionShare): RedirectResponse
    {
        $positionShare->delete();

        return redirect()->route('position-shares.index')->with('success', 'Partage révoqué.');
    }
}
