<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            // Horodatage de debut de la recharge detectee par la telemetrie qui a
            // servi a creer cette ligne. Sert uniquement a ne plus reproposer une
            // recharge deja saisie ; nul pour toutes les saisies manuelles.
            $table->dateTime('telemetry_started_at')->nullable()->after('vehicle_id');
            $table->index('telemetry_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropIndex(['telemetry_started_at']);
            $table->dropColumn('telemetry_started_at');
        });
    }
};
