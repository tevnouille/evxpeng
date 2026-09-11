<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Ce que la voiture a reellement coute et depuis quand, pour la
            // carte "Amortissement" de Ma voiture. Aucune valeur par defaut,
            // comme les autres champs de consommation de cette table : sans
            // eux, la carte n'a simplement rien a calculer.
            $table->decimal('purchase_price', 10, 2)->nullable()->after('diesel_l_per_100km');
            $table->date('purchase_date')->nullable()->after('purchase_price');
            // Kilometrage au compteur a l'achat : 0 pour une voiture neuve,
            // mais une occasion n'a pas commence a 0.
            $table->unsignedInteger('purchase_odometer_km')->nullable()->after('purchase_date');

            // Le "thermique equivalent" n'a pas a etre le meme modele pour
            // deux voitures electriques differentes : le libelle est saisi
            // avec le prix, pas devine par l'application.
            $table->string('thermal_equivalent_label')->nullable()->after('purchase_odometer_km');
            $table->decimal('thermal_equivalent_price', 10, 2)->nullable()->after('thermal_equivalent_label');

            $table->decimal('ev_maintenance_cost', 8, 2)->nullable()->after('thermal_equivalent_price');
            $table->unsignedInteger('ev_maintenance_interval_km')->nullable()->after('ev_maintenance_cost');
            $table->decimal('thermal_maintenance_cost', 8, 2)->nullable()->after('ev_maintenance_interval_km');
            $table->unsignedInteger('thermal_maintenance_interval_km')->nullable()->after('thermal_maintenance_cost');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price',
                'purchase_date',
                'purchase_odometer_km',
                'thermal_equivalent_label',
                'thermal_equivalent_price',
                'ev_maintenance_cost',
                'ev_maintenance_interval_km',
                'thermal_maintenance_cost',
                'thermal_maintenance_interval_km',
            ]);
        });
    }
};
