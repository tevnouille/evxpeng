<?php

namespace App\Console\Commands;

use App\Models\FuelPrice;
use App\Services\FuelPriceAlertNotifier;
use App\Services\FuelPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Met a jour le prix carburant du jour et alerte les comptes dont un seuil
 * personnel est franchi.
 *
 * Distinct de l'appel reactif a ensureToday() (declenche par la premiere page
 * visitee dans la journee) : sans ce passage planifie quotidien, un jour sans
 * visite ne verrait jamais son prix compare a personne.
 */
class CheckFuelPriceAlerts extends Command
{
    protected $signature = 'fuel-prices:check';

    protected $description = 'Recupere le prix carburant du jour si besoin et alerte les seuils franchis';

    public function handle(FuelPriceService $prices, FuelPriceAlertNotifier $notifier): int
    {
        $prices->ensureToday();

        $today = FuelPrice::whereDate('date', Carbon::today())->first();

        if ($today === null) {
            $this->warn("Aucun prix disponible aujourd'hui : rien à comparer.");

            return self::SUCCESS;
        }

        $previous = FuelPrice::where('date', '<', $today->date)->orderByDesc('date')->first();

        $notified = $notifier->notify($today, $previous);

        $this->info(count($notified).' alerte(s) envoyée(s).');

        return self::SUCCESS;
    }
}
