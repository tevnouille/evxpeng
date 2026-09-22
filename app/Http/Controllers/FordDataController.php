<?php

namespace App\Http\Controllers;

use App\Models\FordOAuthToken;
use App\Models\FordTelemetry;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Suivi de l'integration FordConnect (donnees constructeur, hors OBD/MQTT).
 *
 * Meme principe que XpengDataController : cette page affiche ce que la
 * derniere synchronisation planifiee (`ford:sync`) a rapporte, jamais
 * d'appel a l'API depuis une requete HTTP entrante.
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
            'dernierJeton' => FordOAuthToken::where('vin', config('services.ford.vin'))->first(),
            'dernier' => $releves->last(),
            'releves' => $releves,
            'mesures' => $releves->isEmpty() ? [] : $this->mesures($releves),
        ]);
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
