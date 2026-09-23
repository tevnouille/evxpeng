<?php

namespace App\Http\Controllers;

use App\Models\FordOAuthToken;
use App\Models\FordTelemetry;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Suivi de l'integration FordConnect (donnees constructeur, hors OBD/MQTT).
 *
 * Meme principe que XpengDataController : cette page affiche ce que la
 * derniere synchronisation (`ford:sync`) a rapporte, jamais d'appel a
 * l'API sur une simple visite -- seulement sur le bouton explicite de
 * synchroniser().
 */
class FordDataController extends Controller
{
    /** Meme motif que XpengDataController::MAX_POINTS. */
    private const MAX_POINTS = 600;

    /** Meme motif que XpengDataController::JOURS_AFFICHES. */
    private const JOURS_AFFICHES = 30;

    public function index(): View
    {
        $releves = FordTelemetry::where('recorded_at', '>=', now()->subDays(self::JOURS_AFFICHES))
            ->orderBy('recorded_at')
            ->get();

        return view('my_vehicle.ford_data', [
            'isAdmin' => (bool) CurrentUser::get()?->is_admin,
            // Simple lecture : le cache est rempli par ford:sync, jamais ici
            // (voir son commentaire).
            'imageUrl' => Cache::get('ford_vehicle_image_url'),
            'dernierJeton' => FordOAuthToken::where('vin', config('services.ford.vin'))->first(),
            'dernier' => $releves->last(),
            'releves' => $releves,
            'mesures' => $releves->isEmpty() ? [] : $this->mesures($releves),
        ]);
    }

    /**
     * Lance `ford:sync` a la demande. Directement dans la requete,
     * contrairement a Xpeng : un seul GET, quelques secondes.
     *
     * Dit explicitement quand Ford a renvoye le meme releve qu'avant : la
     * voiture ne remonte rien tant qu'elle est a l'arret, et un « releve
     * enregistre » seul laissait croire a une donnee fraiche (confusion deja
     * rencontree sur la temperature exterieure).
     */
    public function synchroniser(): RedirectResponse
    {
        $avant = FordTelemetry::latest('id')->value('recorded_at');

        $code = Artisan::call('ford:sync');
        $sortie = trim(Artisan::output());

        if ($code !== 0) {
            return back()->with('error', $sortie !== '' ? $sortie : 'La synchronisation Ford a échoué.');
        }

        $apres = FordTelemetry::latest('id')->first()?->recorded_at;

        if ($apres === null) {
            return back()->with('success', 'Synchronisation Ford terminée.');
        }

        $horodatage = $apres->timezone(config('app.timezone'))->format('d/m/Y H:i');

        if ($avant !== null && $apres->equalTo($avant)) {
            return back()->with('success', "Rien de nouveau : Ford renvoie toujours le relevé du {$horodatage}. La voiture ne remonte rien tant qu'elle est à l'arrêt.");
        }

        return back()->with('success', "Nouveau relevé Ford récupéré : {$horodatage}.");
    }

    /**
     * @return array<int, array{label: string, unit: ?string, labels: array<int, string>, values: array<int, float>}>
     */
    private function mesures(Collection $releves): array
    {
        $definitions = [
            ['champ' => 'soc', 'label' => 'Batterie (SoC)', 'unite' => '%'],
            ['champ' => 'odometre_km', 'label' => 'Kilométrage', 'unite' => 'km'],
            ['champ' => 'battery_voltage', 'label' => 'Tension batterie 12V', 'unite' => 'V'],
            ['champ' => 'ambient_temp_c', 'label' => 'Température ambiante', 'unite' => '°C'],
            ['champ' => 'outside_temp_c', 'label' => 'Température extérieure', 'unite' => '°C'],
        ];

        $mesures = [];

        foreach ($definitions as $definition) {
            $points = $releves
                ->filter(fn (FordTelemetry $r) => $r->{$definition['champ']} !== null)
                ->values();

            if ($points->isEmpty()) {
                continue;
            }

            $echantillon = $this->echantillonner($points);

            $mesures[] = [
                'label' => $definition['label'],
                'unit' => $definition['unite'],
                'count' => $points->count(),
                'labels' => $echantillon->map(fn (FordTelemetry $r) => $r->recorded_at->timezone(config('app.timezone'))->format('d/m H:i'))->all(),
                'values' => $echantillon->map(fn (FordTelemetry $r) => round((float) $r->{$definition['champ']}, 1))->all(),
            ];
        }

        return $mesures;
    }

    /** @return Collection<int, FordTelemetry> */
    private function echantillonner(Collection $points): Collection
    {
        $total = $points->count();

        if ($total <= self::MAX_POINTS) {
            return $points;
        }

        $pas = (int) ceil($total / self::MAX_POINTS);
        $garde = $points->filter(fn ($p, $i) => $i % $pas === 0)->values();

        return $garde->push($points->last());
    }
}
