<?php

namespace App\Services;

use App\Models\ChargingSession;
use App\Models\FuelPrice;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;

/**
 * Amortissement face a une thermique equivalente : depuis quand rouler en
 * electrique coute moins cher, tout compris (achat, energie, entretien).
 *
 * Extrapole sur l'ensemble des kilometres parcourus depuis l'achat, pas sur
 * une somme de recharges bornee dans le temps : l'application ne trace les
 * recharges que depuis qu'elle existe (ChargingSession), largement apres la
 * date d'achat pour un vehicule possede depuis plusieurs annees. Le prix
 * moyen reellement paye par kWh (et le prix moyen du carburant releve par
 * FuelPriceService) sert donc de taux applique a la distance totale, plutot
 * qu'une somme partielle qui sous-estimerait le cout reel. C'est une
 * estimation, assumee comme telle dans l'affichage.
 */
class PaybackCalculator
{
    /**
     * @return array<string, mixed>|null  Null si des donnees necessaires manquent.
     */
    public function forVehicle(Vehicle $vehicle, ?int $currentOdometerKm): ?array
    {
        if ($vehicle->purchase_price === null
            || $vehicle->purchase_date === null
            || $vehicle->thermal_equivalent_price === null
            || $currentOdometerKm === null) {
            return null;
        }

        $purchaseOdometer = $vehicle->purchase_odometer_km ?? 0;
        $kmDriven = max(0, $currentOdometerKm - $purchaseOdometer);

        $consumption = $vehicle->kwh_per_100km ? (float) $vehicle->kwh_per_100km : null;
        $avgPricePerKwh = $this->avgPricePerKwh($vehicle);

        $electricityCost = ($avgPricePerKwh !== null && $consumption !== null)
            ? $kmDriven / 100 * $consumption * $avgPricePerKwh
            : null;

        // Diesel si renseigne (coherent avec une thermique typiquement diesel
        // sur ce segment), sinon essence. Aucune valeur par defaut sur la
        // consommation elle-meme : sans l'un ou l'autre, pas de comparaison.
        $usesDiesel = (bool) $vehicle->diesel_l_per_100km;
        $fuelPer100km = $usesDiesel
            ? (float) $vehicle->diesel_l_per_100km
            : ($vehicle->essence_l_per_100km ? (float) $vehicle->essence_l_per_100km : null);

        $avgFuelPrice = $usesDiesel
            ? (float) (FuelPrice::avg('diesel_price') ?? FuelPriceService::DEFAULT_DIESEL_PRICE)
            : (float) (FuelPrice::avg('essence_price') ?? FuelPriceService::DEFAULT_ESSENCE_PRICE);

        $fuelCost = $fuelPer100km !== null ? $kmDriven / 100 * $fuelPer100km * $avgFuelPrice : null;

        if ($electricityCost === null || $fuelCost === null) {
            return null;
        }

        $evMaintenance = ($vehicle->ev_maintenance_cost && $vehicle->ev_maintenance_interval_km)
            ? $kmDriven / $vehicle->ev_maintenance_interval_km * (float) $vehicle->ev_maintenance_cost
            : 0.0;

        $thermalMaintenance = ($vehicle->thermal_maintenance_cost && $vehicle->thermal_maintenance_interval_km)
            ? $kmDriven / $vehicle->thermal_maintenance_interval_km * (float) $vehicle->thermal_maintenance_cost
            : 0.0;

        $evTotal = (float) $vehicle->purchase_price + $electricityCost + $evMaintenance;
        $thermalTotal = (float) $vehicle->thermal_equivalent_price + $fuelCost + $thermalMaintenance;

        // Ecart a l'achat (positif = l'electrique coutait plus cher au
        // depart) et avantage par km parcouru (positif = chaque km roule
        // rapproche du seuil de rentabilite, ou l'eloigne s'il etait deja
        // depasse).
        $initialGap = (float) $vehicle->purchase_price - (float) $vehicle->thermal_equivalent_price;
        $perKmAdvantage = $kmDriven > 0
            ? (($fuelCost + $thermalMaintenance) - ($electricityCost + $evMaintenance)) / $kmDriven
            : 0.0;

        $isProfitable = $thermalTotal >= $evTotal;

        // Le km ou les deux courbes de cout se croisent, qu'il soit deja
        // passe (l'electrique a rattrape son surcout initial il y a
        // longtemps) ou encore a venir. Nul si l'entretien+carburant evite ne
        // compense jamais l'ecart de depart (perKmAdvantage <= 0) : aucun
        // kilometre ne rendrait alors la comparaison favorable.
        $breakEvenKm = $perKmAdvantage > 0 ? max(0, (int) ceil($initialGap / $perKmAdvantage)) : null;

        $breakEvenDate = null;

        if ($breakEvenKm !== null) {
            $daysSincePurchase = max(1, $vehicle->purchase_date->diffInDays(now()));
            $kmPerDay = $kmDriven / $daysSincePurchase;

            if ($kmPerDay > 0) {
                $daysFromPurchase = $breakEvenKm / $kmPerDay;
                $breakEvenDate = CarbonImmutable::parse($vehicle->purchase_date)->addDays((int) round($daysFromPurchase));
            }
        }

        return [
            'km_driven' => $kmDriven,
            'purchase_date' => $vehicle->purchase_date,
            'ev_price' => (float) $vehicle->purchase_price,
            'thermal_price' => (float) $vehicle->thermal_equivalent_price,
            'thermal_label' => $vehicle->thermal_equivalent_label,
            'ev_total_cost' => round($evTotal, 2),
            'thermal_total_cost' => round($thermalTotal, 2),
            'savings_to_date' => round($thermalTotal - $evTotal, 2),
            'is_profitable' => $isProfitable,
            'initial_gap' => round($initialGap, 2),
            'break_even_km' => $breakEvenKm,
            'break_even_date' => $breakEvenDate,
            'km_remaining_to_break_even' => $breakEvenKm !== null ? max(0, $breakEvenKm - $kmDriven) : null,
            'electricity_cost' => round($electricityCost, 2),
            'fuel_cost' => round($fuelCost, 2),
            'ev_maintenance' => round($evMaintenance, 2),
            'thermal_maintenance' => round($thermalMaintenance, 2),
            'avg_price_per_kwh' => $avgPricePerKwh !== null ? round($avgPricePerKwh, 3) : null,
            'avg_fuel_price' => round($avgFuelPrice, 3),
            'fuel_price_known' => FuelPrice::exists(),
            'uses_diesel' => $usesDiesel,
        ];
    }

    /**
     * Prix moyen reellement paye par kWh, sur toutes les recharges
     * enregistrees du vehicule (pas seulement depuis l'achat : c'est le taux
     * qui nous interesse, pas la somme).
     */
    private function avgPricePerKwh(Vehicle $vehicle): ?float
    {
        $totals = ChargingSession::where('vehicle_id', $vehicle->id)
            ->where('quantity_kwh', '>', 0)
            ->selectRaw('SUM(quantity_kwh) as kwh, SUM(total_cost) as cost')
            ->first();

        if (! $totals || (float) $totals->kwh <= 0) {
            return null;
        }

        return (float) $totals->cost / (float) $totals->kwh;
    }
}
