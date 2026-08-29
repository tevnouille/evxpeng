<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_telemetries', function (Blueprint $table) {
            // Champs apparus des le branchement d'un dongle OBD : la lecture ne
            // renvoie qu'un sous-ensemble de ce que la *source* a pousse, et le
            // cloud constructeur seul ne fournissait que soc / is_charging / position.
            $table->unsignedInteger('odometer')->nullable()->after('is_connected');
            $table->decimal('soh', 5, 1)->nullable()->after('odometer');
            // Positif en traction, negatif en charge (convention ABRP).
            $table->decimal('power_kw', 8, 3)->nullable()->after('soh');
            $table->decimal('batt_temp', 5, 1)->nullable()->after('power_kw');
            $table->decimal('ext_temp', 5, 1)->nullable()->after('batt_temp');
            $table->decimal('speed', 6, 1)->nullable()->after('ext_temp');
            $table->boolean('is_dcfc')->nullable()->after('speed');
            $table->boolean('is_parked')->nullable()->after('is_dcfc');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_telemetries', function (Blueprint $table) {
            $table->dropColumn(['odometer', 'soh', 'power_kw', 'batt_temp', 'ext_temp', 'speed', 'is_dcfc', 'is_parked']);
        });
    }
};
