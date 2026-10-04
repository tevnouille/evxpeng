<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions de recharge telles que le boitier OBD les a mesurees.
 *
 * A ne pas confondre avec ce que reconstitue TelemetrySessionDetector : ici
 * l'energie n'est pas deduite d'un ecart de SoC, elle vient du compteur
 * `cumulativeCharge` du BMS. L'ecart n'est pas anecdotique — 3,831 kWh mesures
 * la ou le calcul par SoC donnait 2,98, soit 29 % de moins.
 *
 * Table distincte de `charging_sessions` : celle-ci reste la saisie de
 * l'utilisateur, avec ses couts. Celle-la n'est qu'une source de
 * pre-remplissage, que l'on peut vider et reconstruire sans rien perdre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_charging_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            // Identifiant attribue par XPCarData : c'est lui qui rend l'ingestion
            // idempotente, la session etant republiee a chaque reconnexion.
            $table->string('external_id');

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->decimal('soc_start', 5, 1)->nullable();
            $table->decimal('soc_end', 5, 1)->nullable();

            // Mesuree, pas deduite.
            $table->decimal('energy_kwh', 8, 3)->nullable();
            $table->decimal('energy_ah', 8, 2)->nullable();

            $table->unsignedInteger('odometer_start')->nullable();
            $table->unsignedInteger('odometer_end')->nullable();

            $table->string('charging_type', 16)->nullable();
            $table->decimal('max_power_kw', 8, 3)->nullable();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();

            // Courbe reellement relevee sur cette borne : puissance, tension,
            // courant et temperature point par point. Conservee telle quelle,
            // sa forme n'appartient pas a ce schema.
            $table->json('curve')->nullable();
            $table->json('raw')->nullable();

            $table->timestamps();

            $table->unique(['vehicle_id', 'external_id']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_charging_sessions');
    }
};
