<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un releve FordConnect Query (App\Services\FordClient), une ligne par appel
 * planifie -- contrairement a Xpeng (export quotidien agrege a la minute),
 * l'API Ford repond l'etat courant a chaque appel, donc un point par
 * execution de `ford:sync`.
 *
 * `metrics` garde la reponse brute de /v1/telemetry telle quelle : le jeu de
 * champs reellement exposes n'est pas documente publiquement (constate a la
 * verification manuelle du 22/09/2026 : ni position, ni indicateur de charge,
 * contrairement a ce que d'autres integrations tierces laissent supposer) --
 * mieux vaut tout garder et n'exposer que ce qui est confirme dans les
 * colonnes dediees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ford_telemetries', function (Blueprint $table) {
            $table->id();
            $table->string('vin');
            $table->timestampTz('recorded_at');

            $table->float('soc')->nullable();
            $table->float('odometre_km')->nullable();
            $table->float('battery_voltage')->nullable();
            $table->float('ambient_temp_c')->nullable();
            $table->float('outside_temp_c')->nullable();
            $table->string('ignition_status')->nullable();

            $table->json('metrics');

            $table->timestampsTz();

            $table->index(['vin', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ford_telemetries');
    }
};
