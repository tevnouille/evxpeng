<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            // Position exacte de la borne utilisee, reprise de la telemetrie au
            // moment de la saisie. Une localisation peut ainsi compter autant de
            // points que de bornes, la ou locations.latitude/longitude n'en garde
            // qu'un seul, forcement approximatif des qu'une ville a deux bornes.
            $table->decimal('latitude', 10, 7)->nullable()->after('power_rating_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
