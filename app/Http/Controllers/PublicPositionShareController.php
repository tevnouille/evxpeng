<?php

namespace App\Http\Controllers;

use App\Models\PositionShare;
use App\Services\ChargingCurveRepository;
use App\Services\ReverseGeocoder;
use Illuminate\View\View;

/**
 * Vue du destinataire d'un lien de partage de position.
 *
 * Servie sans identite applicative : App\Http\Middleware\IdentifyUser laisse
 * passer explicitement la route position-shares.show (voir son commentaire),
 * et le vhost dedie de s.lolinux.fr ne passe pas par la passerelle passkey —
 * cf. docker/share/README.md. Le token, genere aleatoirement et jamais
 * devinable, est la seule protection : exactement celle d'un lien de partage
 * classique.
 */
class PublicPositionShareController extends Controller
{
    public function __construct(
        private readonly ChargingCurveRepository $curves,
        private readonly ReverseGeocoder $geocoder,
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
            ];
        })->values();

        return view('position_shares.public', [
            'vehicle' => $vehicle,
            'share' => $share,
            'mapPoints' => $mapPoints,
        ]);
    }
}
