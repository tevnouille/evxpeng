<?php

namespace App\Http\Controllers;

use App\Models\FuelPrice;
use App\Services\FuelPriceService;
use Illuminate\View\View;

class FuelPriceController extends Controller
{
    public function index(FuelPriceService $fuelPriceService): View
    {
        $fuelPriceService->ensureToday();

        return view('fuel_prices.index', [
            'prices' => FuelPrice::orderByDesc('date')->get(),
        ]);
    }
}
