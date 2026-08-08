<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Vehicle;
use App\Services\FuelPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard.index', [
            'vehicles' => Vehicle::orderBy('name')->get(),
        ]);
    }

    public function data(Request $request, FuelPriceService $fuelPriceService): JsonResponse
    {
        $fuelPriceService->ensureToday();

        $granularity = $request->query('granularity', 'month');
        $vehicleId = $request->query('vehicle_id');

        $periodExpr = match ($granularity) {
            'day' => "DATE_FORMAT(session_date, '%Y-%m-%d')",
            'week' => "CONCAT(YEAR(session_date), '-S', LPAD(WEEK(session_date, 3), 2, '0'))",
            'year' => 'YEAR(session_date)',
            default => "DATE_FORMAT(session_date, '%Y-%m')",
        };

        $rows = ChargingSession::selectRaw("$periodExpr as period, MIN(session_date) as period_start, SUM(quantity_kwh) as kwh, SUM(total_cost) as cost, COUNT(*) as sessions_count")
            ->when($vehicleId, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->groupBy('period')
            ->orderBy('period_start')
            ->get();

        $byProvider = ChargingSession::query()
            ->join('providers', 'providers.id', '=', 'charging_sessions.provider_id')
            ->when($vehicleId, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->selectRaw('providers.name as name, SUM(quantity_kwh) as kwh, SUM(total_cost) as cost')
            ->groupBy('providers.id', 'providers.name')
            ->orderByDesc('kwh')
            ->get();

        $fuelEquivalentCostByPeriod = $rows->map(function ($row) use ($fuelPriceService) {
            $prices = $fuelPriceService->pricesForDate($row->period_start);
            $liters = $fuelPriceService->equivalentLiters((float) $row->kwh);

            return round($liters * $prices['essence'], 2);
        });

        $totalKwh = (float) $rows->sum('kwh');
        $totalElectricCost = (float) $rows->sum('cost');
        $totalLiters = $fuelPriceService->equivalentLiters($totalKwh);
        $totalFuelCost = (float) $fuelEquivalentCostByPeriod->sum();
        $todayPrices = $fuelPriceService->pricesForDate(now());

        return response()->json([
            'granularity' => $granularity,
            'labels' => $rows->pluck('period')->values(),
            'kwh' => $rows->pluck('kwh')->map(fn ($v) => (float) $v)->values(),
            'cost' => $rows->pluck('cost')->map(fn ($v) => (float) $v)->values(),
            'avg_cost_per_kwh' => $rows->map(fn ($r) => $r->kwh > 0 ? round($r->cost / $r->kwh, 4) : 0)->values(),
            'sessions_count' => $rows->pluck('sessions_count')->map(fn ($v) => (int) $v)->values(),
            'fuel_equivalent_cost' => $fuelEquivalentCostByPeriod->values(),
            'fuel_equivalent' => [
                'liters' => round($totalLiters, 2),
                'cost' => round($totalFuelCost, 2),
                'essence_price' => $todayPrices['essence'],
                'diesel_price' => $todayPrices['diesel'],
                'estimated' => $todayPrices['estimated'],
                'savings' => round($totalFuelCost - $totalElectricCost, 2),
            ],
            'by_provider' => $byProvider->map(fn ($p) => [
                'name' => $p->name,
                'kwh' => (float) $p->kwh,
                'cost' => (float) $p->cost,
            ])->values(),
        ]);
    }
}
