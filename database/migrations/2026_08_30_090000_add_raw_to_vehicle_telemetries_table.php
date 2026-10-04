<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_telemetries', function (Blueprint $table) {
            // get_telemetry ne renvoie qu'un sous-ensemble des proprietes, et ce
            // sous-ensemble semble dependre de l'etat du vehicule (a l'arret on
            // n'observe que soc / is_charging / lat / lon). On conserve la reponse
            // brute pour ne pas perdre les champs qui apparaitraient en roulage ou
            // en charge avant qu'on sache lesquels modeliser.
            $table->json('raw')->nullable()->after('telemetry_type');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_telemetries', function (Blueprint $table) {
            $table->dropColumn('raw');
        });
    }
};
