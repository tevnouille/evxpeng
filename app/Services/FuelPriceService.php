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
     * Regroupe des recharges (chacune avec session_date + quantity_kwh) par une clé
     * arbitraire, en appliquant à chacune le prix carburant réel de SA date de recharge
     * (et non un prix moyen ou une date de bucket approximative).
     *
     * @param iterable<object{session_date: mixed, quantity_kwh: mixed}> $rows
     * @return array<string, array{liters: float, essence_cost: float, diesel_cost: float, known: int, count: int}>
     */
    public function equivalentByGroup(iterable $rows, callable $groupKeyFn): array
    {
        $prices = FuelPrice::all()->keyBy(fn ($p) => $p->date->format('Y-m-d'));
        $groups = [];

        foreach ($rows as $row) {
            $key = $groupKeyFn($row);
            $dateKey = Carbon::parse($row->session_date)->format('Y-m-d');
            $priceRow = $prices->get($dateKey);
            $essencePrice = $priceRow ? (float) $priceRow->essence_price : self::DEFAULT_ESSENCE_PRICE;
            $dieselPrice = $priceRow ? (float) $priceRow->diesel_price : self::DEFAULT_DIESEL_PRICE;
            $liters = $this->equivalentLiters((float) $row->quantity_kwh);

            $groups[$key] ??= ['liters' => 0.0, 'essence_cost' => 0.0, 'diesel_cost' => 0.0, 'known' => 0, 'count' => 0];
            $groups[$key]['liters'] += $liters;
            $groups[$key]['essence_cost'] += $liters * $essencePrice;
            $groups[$key]['diesel_cost'] += $liters * $dieselPrice;
            $groups[$key]['known'] += $priceRow ? 1 : 0;
            $groups[$key]['count'] += 1;
        }

        return $groups;
    }

    /**
     * @param iterable<object{session_date: mixed, quantity_kwh: mixed}> $rows
     * @return array{liters: float, essence_cost: float, diesel_cost: float, known_price_sessions: int, total_sessions: int, estimated: bool}
     */
    public function equivalentTotals(iterable $rows): array
    {
        $groups = $this->equivalentByGroup($rows, fn () => 'all');
        $g = $groups['all'] ?? ['liters' => 0.0, 'essence_cost' => 0.0, 'diesel_cost' => 0.0, 'known' => 0, 'count' => 0];

        return [
            'liters' => round($g['liters'], 2),
            'essence_cost' => round($g['essence_cost'], 2),
            'diesel_cost' => round($g['diesel_cost'], 2),
            'known_price_sessions' => $g['known'],
            'total_sessions' => $g['count'],
            'estimated' => $g['count'] > 0 && $g['known'] < $g['count'],
        ];
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
