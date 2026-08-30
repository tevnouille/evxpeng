<?php

namespace App\Http\Controllers;

use App\Models\ChargingStation;
use App\Models\FavoriteRoute;
use App\Models\FavoriteRouteStation;
use App\Services\Geocoder;
use App\Services\RouteCorridor;
use App\Services\RouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trajets favoris : les parcours qu'on refait, avec les bornes qu'on y a
 * retenues et les liens de navigation qui vont avec.
 *
 * Contrairement au planificateur, rien n'est decide pour l'utilisateur : la
 * carte montre toutes les bornes du corridor, il choisit lui-meme.
 */
class FavoriteRouteController extends Controller
{
    /** Puissances minimales proposees, en kW. */
    public const MIN_POWERS = [0, 22, 50, 100, 150, 200, 300];

    public function __construct(
        private readonly Geocoder $geocoder,
        private readonly RouteService $router,
        private readonly RouteCorridor $corridor,
    ) {
    }

    public function index(): View
    {
        return view('favorites.index', [
            'routes' => FavoriteRoute::withCount('stations')->orderBy('name')->get(),
            'minPowers' => self::MIN_POWERS,
            'stationCount' => ChargingStation::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'depart' => ['required', 'string', 'max:190'],
            'arrivee' => ['required', 'string', 'max:190'],
            'puissance_min' => ['nullable', 'numeric', 'between:0,400'],
            'detour' => ['nullable', 'numeric', 'between:1,30'],
            'depart_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'depart_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'arrivee_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'arrivee_lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Point exact retenu dans la liste de suggestions, s'il y en a un.
        $from = $this->geocoder->resolve($data['depart'], $data['depart_lat'] ?? null, $data['depart_lon'] ?? null);
        $to = $this->geocoder->resolve($data['arrivee'], $data['arrivee_lat'] ?? null, $data['arrivee_lon'] ?? null);

        if ($from === null || $to === null) {
            return back()->withInput()->with('error', $from === null
                ? 'Adresse de départ introuvable.'
                : 'Adresse d\'arrivée introuvable.');
        }

        $route = $this->router->route($from, $to);

        if ($route === null || $route['coordinates'] === []) {
            return back()->withInput()->with('error', 'Aucun itinéraire routier trouvé entre ces deux points.');
        }

        $favorite = FavoriteRoute::create([
            'name' => $data['nom'],
            'from_label' => $from['label'],
            'from_lat' => $from['lat'],
            'from_lon' => $from['lon'],
            'to_label' => $to['label'],
            'to_lat' => $to['lat'],
            'to_lon' => $to['lon'],
            'min_power_kw' => $data['puissance_min'] ?? 50,
            'max_detour_km' => $data['detour'] ?? 5,
            'distance_km' => $route['distance_km'],
            'duration_minutes' => (int) round($route['duration_minutes']),
            'geometry' => $this->corridor->simplify($route['coordinates']),
        ]);

        return redirect()->route('favorites.show', $favorite)
            ->with('success', 'Trajet enregistré. Cliquez sur une borne de la carte pour l\'ajouter.');
    }

    public function show(FavoriteRoute $favorite): View
    {
        $favorite->load('stations');

        return view('favorites.show', [
            'route' => $favorite,
            'stations' => $this->corridorStations($favorite),
            'minPowers' => self::MIN_POWERS,
            'networks' => ChargingStation::networkOptions(),
        ]);
    }

    /** Renommage et reglage des filtres ; l'itineraire lui-meme ne change pas. */
    public function update(Request $request, FavoriteRoute $favorite): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'puissance_min' => ['nullable', 'numeric', 'between:0,400'],
            'detour' => ['nullable', 'numeric', 'between:1,30'],
            'reseaux' => ['nullable', 'array'],
            'reseaux.*' => ['string', 'max:120'],
        ]);

        $networks = array_values(array_filter($data['reseaux'] ?? []));

        $favorite->update([
            'name' => $data['nom'],
            'min_power_kw' => $data['puissance_min'] ?? $favorite->min_power_kw,
            'max_detour_km' => $data['detour'] ?? $favorite->max_detour_km,
            'networks' => $networks !== [] ? $networks : null,
        ]);

        return redirect()->route('favorites.show', $favorite)->with('success', 'Trajet mis à jour.');
    }

    public function destroy(FavoriteRoute $favorite): RedirectResponse
    {
        $favorite->delete();

        return redirect()->route('favorites.index')->with('success', 'Trajet supprimé.');
    }

    /** Ajout d'une borne aux favoris du trajet, depuis la carte. */
    public function addStation(Request $request, FavoriteRoute $favorite): JsonResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer', 'exists:charging_stations,id'],
            'km' => ['nullable', 'numeric'],
        ]);

        $station = ChargingStation::findOrFail($data['station_id']);

        $favorite->stations()->updateOrCreate(
            ['charging_station_id' => $station->id],
            [
                'name' => $station->name,
                'network' => $station->network ?: $station->operator,
                'address' => $station->address,
                'city' => $station->city,
                'lat' => $station->lat,
                'lon' => $station->lon,
                'power_kw' => $station->max_power_kw,
                'km' => $data['km'] ?? null,
            ]
        );

        return $this->stationsPayload($favorite);
    }

    public function removeStation(FavoriteRoute $favorite, FavoriteRouteStation $station): JsonResponse
    {
        abort_unless($station->favorite_route_id === $favorite->id, 404);

        $station->delete();

        return $this->stationsPayload($favorite);
    }

    /**
     * Bornes du corridor, dans la forme attendue par la carte.
     *
     * @return array<int, array<string, mixed>>
     */
    private function corridorStations(FavoriteRoute $favorite): array
    {
        $geometry = $favorite->geometry ?? [];

        if ($geometry === []) {
            return [];
        }

        $samples = $this->corridor->sample($geometry);

        return collect($this->corridor->stations($samples, [
            'min_power' => $favorite->min_power_kw,
            'max_detour_km' => $favorite->max_detour_km,
            // Filtre ferme, contrairement au planificateur : ici le choix des
            // reseaux sert a alleger la carte, pas a departager des candidats.
            'networks' => $favorite->networks ?? [],
            'networks_only' => true,
        ]))->map(fn ($entry) => $entry['station'] + [
            'km' => $entry['km'],
            'detour_km' => $entry['detour_km'],
        ])->all();
    }

    private function stationsPayload(FavoriteRoute $favorite): JsonResponse
    {
        $favorite->load('stations');

        return response()->json([
            'stations' => $favorite->stations->map(fn ($station) => [
                'id' => $station->id,
                'station_id' => $station->charging_station_id,
                'name' => $station->name,
                'network' => $station->network,
                'address' => $station->address,
                'city' => $station->city,
                'lat' => $station->lat,
                'lon' => $station->lon,
                'power_kw' => $station->power_kw,
                'km' => $station->km,
                'google_url' => $station->googleMapsUrl(),
                'waze_url' => $station->wazeUrl(),
            ])->values(),
            'route_google_url' => $favorite->googleMapsUrl(),
        ]);
    }
}
