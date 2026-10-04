<?php

namespace App\Http\Controllers;

use App\Models\PositionShare;
use App\Models\VehicleTelemetry;
use App\Services\ChargeCurveSimulator;
use App\Services\ChargingCurveRepository;
use App\Services\ReverseGeocoder;
use Illuminate\View\View;

/**
 * Vue du destinataire d'un lien de partage de position.
 *
 * Servie sans session : App\Http\Middleware\IdentifyUser laisse passer
 * explicitement la route position-shares.show (voir son commentaire). Le token, genere aleatoirement et jamais
 * devinable, est la seule protection : exactement celle d'un lien de partage
 * classique.
 */
class PublicPositionShareController extends Controller
{
    public function __construct(
        private readonly ChargingCurveRepository $curves,
        private readonly ReverseGeocoder $geocoder,
        private readonly ChargeCurveSimulator $simulator,
    ) {
    }

    public function show(string $token): View
    {
        $share = PositionShare::where('token', $token)->first();

        abort_if($share === null, 404);

        if ($share->isExpired()) {
            return view('position_shares.expired', ['share' => $share]);
        }

        $vehicle = $share->vehicle;

        // Depuis la creation du partage seulement : celui qui recoit le lien
        // vient voir ou va la voiture maintenant, pas le trajet du matin.
        $points = $vehicle->telemetries()
            ->whereNotNull('lat')
            ->where('recorded_at', '>=', $share->created_at)
            ->orderBy('recorded_at')
            ->get();

        // Le tout premier relevé posterieur au partage peut manquer une
        // vingtaine de secondes (cadence du boitier) : sans repli, le lien
        // ouvert dans cette fenetre affichait "aucun relevé" alors que la
        // position etait connue. Le dernier point connu, meme anterieur au
        // partage, vaut mieux qu'une page vide — signale comme tel dans la
        // vue plutot que de laisser croire qu'il est aussi frais que les
        // autres.
        $usingFallback = false;

        if ($points->isEmpty()) {
            $fallback = $vehicle->telemetries()
                ->whereNotNull('lat')
                ->orderByDesc('recorded_at')
                ->first();

            if ($fallback !== null) {
                $points = collect([$fallback]);
                $usingFallback = true;
            }
        }

        $this->geocoder->resolveMissing($points);
        $adresses = $this->geocoder->known($points);

        $curve = $this->curves->forVehicle($vehicle);
        $netCapacity = $curve['battery_net_kwh'] ?? null;
        $consumption = $vehicle->kwh_per_100km ? (float) $vehicle->kwh_per_100km : null;

        $mapPoints = $points->values()->map(function ($row) use ($adresses, $netCapacity, $consumption) {
            $soc = $row->soc !== null ? (float) $row->soc : null;
            $availableKwh = ($soc !== null && $netCapacity) ? $soc / 100 * $netCapacity : null;
            $rangeKm = ($availableKwh !== null && $consumption) ? (int) round($availableKwh / $consumption * 100) : null;
            $place = $adresses[$this->geocoder->key((float) $row->lat, (float) $row->lon)] ?? null;

            return [
                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'time' => $row->recorded_at->format('H:i:s'),
                'soc' => $soc,
                'range_km' => $rangeKm,
                'address' => ($place && $place->label !== '') ? $place->label : null,
                'charging' => (bool) $row->is_charging,
            ];
        })->values();

        return view('position_shares.public', [
            'vehicle' => $vehicle,
            'share' => $share,
            'mapPoints' => $mapPoints,
            'usingFallback' => $usingFallback,
            // Etat courant, independant de la fenetre de points affichee : le
            // destinataire du lien veut savoir si la voiture charge *maintenant*,
            // pas si elle chargeait au tout debut du partage.
            'charging' => $this->chargingNow($vehicle->latestTelemetry, $curve, $netCapacity),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $curve
     * @return array{power_kw: float, soc: float, remaining_minutes: int|null}|null
     */
    private function chargingNow(?VehicleTelemetry $latest, ?array $curve, ?float $netCapacity): ?array
    {
        if ($latest === null || ! $latest->is_charging || $latest->soc === null) {
            return null;
        }

        $soc = (float) $latest->soc;
        $power = $latest->power_kw !== null ? abs((float) $latest->power_kw) : null;

        $remaining = ($power !== null && $power > 0 && $curve !== null && $netCapacity)
            ? $this->simulator->duration($curve, $soc, 100.0, $power)
            : null;

        return [
            'power_kw' => $power ?? 0.0,
            'soc' => $soc,
            'remaining_minutes' => $remaining !== null ? (int) round($remaining / 60) : null,
        ];
    }
}
