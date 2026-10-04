<?php

namespace App\Http\Controllers;

use App\Models\ChargingStation;
use App\Models\Vehicle;
use App\Services\ChargingCurveRepository;
use App\Services\Geocoder;
use App\Services\RoutePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoutePlannerController extends Controller
{
    /** Puissances minimales de borne proposees au filtre, en kW. */
    public const MIN_POWERS = [0, 22, 50, 100, 150, 200, 300];

    /** Plafonds de charge proposes : au-dela de 80 % la courbe s'ecroule. */
    public const MAX_SOCS = [70, 80, 90, 100];

    public function __construct(
        private readonly ChargingCurveRepository $curves,
        private readonly RoutePlanner $planner,
        private readonly Geocoder $geocoder,
    ) {
    }

    public function index(Request $request): View
    {
        // Comme pour /courbe-de-recharge : sans courbe associee, aucun temps de
        // charge n'est calculable, le vehicule n'a rien a faire dans la liste.
        $vehicles = Vehicle::whereNotNull('charging_curve')
            ->with('latestTelemetry')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->filter(fn ($vehicle) => $this->curves->find($vehicle->charging_curve) !== null)
            ->values();

        $vehicle = $vehicles->firstWhere('id', (int) $request->query('vehicule')) ?? $vehicles->first();

        $stationCount = ChargingStation::count();
        $form = $this->form($request, $vehicle);
        $plan = null;

        if ($vehicle && $stationCount > 0 && $form['from'] !== '' && $form['to'] !== '') {
            $validated = $request->validate([
                'depart' => ['required', 'string', 'max:190'],
                'arrivee' => ['required', 'string', 'max:190'],
                'soc_depart' => ['nullable', 'numeric', 'between:1,100'],
                'soc_arrivee' => ['nullable', 'numeric', 'between:0,80'],
                'reserve' => ['nullable', 'numeric', 'between:0,40'],
                'soc_max' => ['nullable', 'numeric', 'between:50,100'],
                'puissance_min' => ['nullable', 'numeric', 'between:0,400'],
                'detour' => ['nullable', 'numeric', 'between:1,30'],
                'consommation' => ['nullable', 'numeric', 'between:5,40'],
                'reseaux' => ['nullable', 'array'],
                'reseaux.*' => ['string', 'max:120'],
                'depart_lat' => ['nullable', 'numeric', 'between:-90,90'],
                'depart_lon' => ['nullable', 'numeric', 'between:-180,180'],
                'arrivee_lat' => ['nullable', 'numeric', 'between:-90,90'],
                'arrivee_lon' => ['nullable', 'numeric', 'between:-180,180'],
                'reseaux_only' => ['nullable', 'boolean'],
            ]);

            $plan = $this->planner->plan($vehicle, [
                'from' => $validated['depart'],
                'to' => $validated['arrivee'],
                // Point exact retenu dans la liste de suggestions, s'il y en a un.
                'from_lat' => $validated['depart_lat'] ?? null,
                'from_lon' => $validated['depart_lon'] ?? null,
                'to_lat' => $validated['arrivee_lat'] ?? null,
                'to_lon' => $validated['arrivee_lon'] ?? null,
                'start_soc' => $form['start_soc'],
                'arrival_soc' => $form['arrival_soc'],
                'reserve_soc' => $form['reserve_soc'],
                'max_soc' => $form['max_soc'],
                'min_power' => $form['min_power'],
                'max_detour_km' => $form['max_detour_km'],
                'consumption' => $form['consumption'],
                'networks' => $form['networks'],
                'networks_only' => $form['networks_only'],
            ]);
        }

        return view('planner.index', [
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'form' => $form,
            'plan' => $plan,
            'networks' => ChargingStation::networkOptions(),
            'minPowers' => self::MIN_POWERS,
            'maxSocs' => self::MAX_SOCS,
            'stationCount' => $stationCount,
            'stationsUpdatedAt' => ChargingStation::max('updated_at'),
        ]);
    }

    /** Saisie assistee des adresses, adossee a la Base Adresse Nationale. */
    public function suggestions(Request $request): JsonResponse
    {
        return response()->json([
            'results' => $this->geocoder->suggest((string) $request->query('q', '')),
        ]);
    }

    /**
     * Valeurs du formulaire : la requete d'abord, puis le vehicule, puis des
     * valeurs par defaut raisonnables.
     *
     * @return array<string, mixed>
     */
    private function form(Request $request, ?Vehicle $vehicle): array
    {
        $telemetrySoc = $vehicle?->latestTelemetry?->soc;

        return [
            'from' => trim((string) $request->query('depart', '')),
            'to' => trim((string) $request->query('arrivee', '')),
            // Le niveau reel de la voiture est le meilleur point de depart
            // possible ; il reste modifiable pour simuler un trajet futur.
            'start_soc' => (float) ($request->query('soc_depart') ?? ($telemetrySoc !== null ? round((float) $telemetrySoc) : 90)),
            'arrival_soc' => (float) ($request->query('soc_arrivee') ?? 15),
            'reserve_soc' => (float) ($request->query('reserve') ?? 10),
            'max_soc' => (float) ($request->query('soc_max') ?? 80),
            'min_power' => (float) ($request->query('puissance_min') ?? 50),
            'max_detour_km' => (float) ($request->query('detour') ?? 5),
            'consumption' => (float) ($request->query('consommation') ?? $vehicle?->kwh_per_100km ?? 18),
            'networks' => array_values(array_filter((array) $request->query('reseaux', []), 'is_string')),
            'networks_only' => $request->boolean('reseaux_only'),
            'telemetry_soc' => $telemetrySoc !== null ? (float) $telemetrySoc : null,
        ];
    }
}
