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
     * Rappelle le service et reecrit le releve du jour.
     *
     * `ensureToday()` s'arrete des qu'une ligne existe : c'est ce qu'il faut au
     * fil de l'eau, mais pas pour une relance manuelle, qui doit justement
     * pouvoir corriger un releve deja enregistre.
     */
    public function refreshToday(): bool
    {
        $prices = $this->fetchNationalAverages();

        if ($prices === null) {
            return false;
        }

        FuelPrice::updateOrCreate(
            ['date' => Carbon::today()],
            ['essence_price' => $prices['essence'], 'diesel_price' => $prices['diesel']],
        );

        return true;
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

    /**
     * Regroupe des recharges par une clé arbitraire, en appliquant à chacune :
     * - le prix carburant réel de SA date de recharge (pas une moyenne ou une date de bucket approximative) ;
     * - la consommation propre à SON véhicule (kwh_per_100km / essence_l_per_100km / diesel_l_per_100km).
     *
     * Aucune valeur par défaut n'est appliquée : un véhicule sans consommation renseignée
     * (dans /admin/vehicules) n'entre simplement pas dans le calcul essence/diesel de ses recharges
     * (mais reste compté dans `count`, pour rester cohérent avec le nombre réel de recharges).
     *
     * @param iterable<object{session_date: mixed, quantity_kwh: mixed, kwh_per_100km: mixed, essence_l_per_100km: mixed, diesel_l_per_100km: mixed}> $rows
     * @return array<string, array{km: float, essence_liters: float, diesel_liters: float, essence_cost: float, diesel_cost: float, known: int, configured: int, count: int}>
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

            $groups[$key] ??= ['km' => 0.0, 'essence_liters' => 0.0, 'diesel_liters' => 0.0, 'essence_cost' => 0.0, 'diesel_cost' => 0.0, 'known' => 0, 'configured' => 0, 'count' => 0];
            $groups[$key]['count']++;
            $groups[$key]['known'] += $priceRow ? 1 : 0;

            $kwhPer100km = $row->kwh_per_100km !== null ? (float) $row->kwh_per_100km : null;

            if (! $kwhPer100km) {
                continue; // vehicule sans consommation configuree : pas d'equivalent calculable
            }

            $groups[$key]['configured']++;
            $equivalentKm = ((float) $row->quantity_kwh / $kwhPer100km) * 100;
            $groups[$key]['km'] += $equivalentKm;

            if ($row->essence_l_per_100km !== null) {
                $essenceLiters = $equivalentKm / 100 * (float) $row->essence_l_per_100km;
                $groups[$key]['essence_liters'] += $essenceLiters;
                $groups[$key]['essence_cost'] += $essenceLiters * $essencePrice;
            }

            if ($row->diesel_l_per_100km !== null) {
                $dieselLiters = $equivalentKm / 100 * (float) $row->diesel_l_per_100km;
                $groups[$key]['diesel_liters'] += $dieselLiters;
                $groups[$key]['diesel_cost'] += $dieselLiters * $dieselPrice;
            }
        }

        return $groups;
    }

    /**
     * @param iterable<object{session_date: mixed, quantity_kwh: mixed, kwh_per_100km: mixed, essence_l_per_100km: mixed, diesel_l_per_100km: mixed}> $rows
     * @return array{km: float, essence_liters: float, diesel_liters: float, essence_cost: float, diesel_cost: float, known_price_sessions: int, configured_sessions: int, total_sessions: int, estimated: bool}
     */
    public function equivalentTotals(iterable $rows): array
    {
        $groups = $this->equivalentByGroup($rows, fn () => 'all');
        $g = $groups['all'] ?? ['km' => 0.0, 'essence_liters' => 0.0, 'diesel_liters' => 0.0, 'essence_cost' => 0.0, 'diesel_cost' => 0.0, 'known' => 0, 'configured' => 0, 'count' => 0];

        return [
            'km' => round($g['km'], 1),
            'essence_liters' => round($g['essence_liters'], 2),
            'diesel_liters' => round($g['diesel_liters'], 2),
            'essence_cost' => round($g['essence_cost'], 2),
            'diesel_cost' => round($g['diesel_cost'], 2),
            'known_price_sessions' => $g['known'],
            'configured_sessions' => $g['configured'],
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
