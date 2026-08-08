<?php

namespace App\Services;

use App\Models\FuelPrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FuelPriceService
{
    // 15 kWh consommés ~= 6 L d'essence pour 100 km (ratio approximatif fourni par l'utilisateur).
    public const KWH_TO_LITERS_RATIO = 6 / 15;

    public const DEFAULT_ESSENCE_PRICE = 1.95;
    public const DEFAULT_DIESEL_PRICE = 1.95;

    private const API_URL = 'https://data.economie.gouv.fr/api/explore/v2.1/catalog/datasets/prix-des-carburants-en-france-flux-instantane-v2/records';

    public function ensureToday(): void
    {
        $today = Carbon::today();

        if (FuelPrice::whereDate('date', $today)->exists()) {
            return;
        }

        $prices = $this->fetchNationalAverages();

        if ($prices === null) {
            return;
        }

        FuelPrice::create([
            'date' => $today,
            'essence_price' => $prices['essence'],
            'diesel_price' => $prices['diesel'],
        ]);
    }

    /**
     * @return array{essence: float, diesel: float, estimated: bool}
     */
    public function pricesForDate(CarbonInterface|string $date): array
    {
        $date = Carbon::parse($date)->startOfDay();

        $row = FuelPrice::whereDate('date', $date)->first();

        if ($row) {
            return [
                'essence' => (float) $row->essence_price,
                'diesel' => (float) $row->diesel_price,
                'estimated' => false,
            ];
        }

        return [
            'essence' => self::DEFAULT_ESSENCE_PRICE,
            'diesel' => self::DEFAULT_DIESEL_PRICE,
            'estimated' => true,
        ];
    }

    public function equivalentLiters(float $kwh): float
    {
        return $kwh * self::KWH_TO_LITERS_RATIO;
    }

    /**
     * Moyenne nationale instantanée SP95 / Gazole via l'API ouverte
     * data.economie.gouv.fr (pas d'authentification, pas d'historique disponible :
     * on l'appelle une fois par jour et on garde le résultat dans fuel_prices).
     *
     * @return array{essence: float, diesel: float}|null
     */
    public function fetchNationalAverages(): ?array
    {
        try {
            $response = Http::timeout(5)->get(self::API_URL, [
                'select' => 'avg(sp95_prix) as avg_sp95, avg(gazole_prix) as avg_gazole',
                'limit' => 1,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $row = $response->json('results.0');

            if (! $row || $row['avg_sp95'] === null || $row['avg_gazole'] === null) {
                return null;
            }

            return [
                'essence' => round((float) $row['avg_sp95'], 3),
                'diesel' => round((float) $row['avg_gazole'], 3),
            ];
        } catch (Throwable $e) {
            Log::warning('FuelPriceService: échec de récupération des prix carburants — ' . $e->getMessage());

            return null;
        }
    }
}
