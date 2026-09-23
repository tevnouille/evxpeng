<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes ouvertes par le scope evData, absent jusqu'au 23/09/2026 (voir
 * FordClient/FordAuthController) : jusque-la, seul batteryStateOfCharge
 * (batterie 12V, PRIMARY_BATTERY) etait accessible, jamais la vraie batterie
 * de traction. Meme principe que xpeng_telemetries : une colonne dediee par
 * mesure numerique exploitable en graphique, le reste (portes, vitres,
 * ceintures, alarme...) restant uniquement dans la colonne `metrics` deja
 * existante -- pas de forme scalaire adaptee a un graphique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ford_telemetries', function (Blueprint $table) {
            $table->float('xev_soc')->nullable()->after('soc');
            $table->float('xev_range_km')->nullable()->after('xev_soc');
            $table->float('xev_energy_remaining_kwh')->nullable()->after('xev_range_km');
            $table->float('xev_time_to_full_charge_min')->nullable()->after('xev_energy_remaining_kwh');
            $table->float('xev_charger_energy_output_kwh')->nullable()->after('xev_time_to_full_charge_min');
            $table->float('xev_charger_current_output_a')->nullable()->after('xev_charger_energy_output_kwh');
            $table->float('xev_charger_voltage_output_v')->nullable()->after('xev_charger_current_output_a');
            $table->string('plug_status')->nullable()->after('ignition_status');
            $table->string('charge_display_status')->nullable()->after('plug_status');
            $table->string('charge_station_power_type')->nullable()->after('charge_display_status');

            // Vitesse : suppose km/h par coherence avec le reste de l'API pour
            // ce compte (region EU, temperatures en Celsius, pression en kPa
            // -- voir pression_*_kpa) mais jamais confirme par une valeur
            // non nulle, le vehicule etant a l'arret a chaque verification.
            $table->float('speed_kmh')->nullable()->after('charge_station_power_type');

            $table->double('latitude', 10, 6)->nullable()->after('speed_kmh');
            $table->double('longitude', 10, 6)->nullable()->after('latitude');

            // Meme unite brute que xpeng_telemetries.pression_*_kpa (kPa),
            // confirmee ici par wheelPlacardFront/Rear (248/338) qui
            // correspondent aux valeurs de plaque constructeur usuelles --
            // conversion en bar a l'affichage seulement, comme pour Xpeng.
            $table->float('pression_av_gauche_kpa')->nullable()->after('longitude');
            $table->float('pression_av_droite_kpa')->nullable()->after('pression_av_gauche_kpa');
            $table->float('pression_ar_gauche_kpa')->nullable()->after('pression_av_droite_kpa');
            $table->float('pression_ar_droite_kpa')->nullable()->after('pression_ar_gauche_kpa');
        });
    }

    public function down(): void
    {
        Schema::table('ford_telemetries', function (Blueprint $table) {
            $table->dropColumn([
                'xev_soc', 'xev_range_km', 'xev_energy_remaining_kwh',
                'xev_time_to_full_charge_min', 'xev_charger_energy_output_kwh',
                'xev_charger_current_output_a', 'xev_charger_voltage_output_v',
                'plug_status', 'charge_display_status', 'charge_station_power_type',
                'speed_kmh', 'latitude', 'longitude',
                'pression_av_gauche_kpa', 'pression_av_droite_kpa',
                'pression_ar_gauche_kpa', 'pression_ar_droite_kpa',
            ]);
        });
    }
};
