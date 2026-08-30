<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base des bornes de recharge, importee de la base nationale IRVE (data.gouv.fr).
 *
 * Une ligne par station, pas par point de charge : le planificateur raisonne en
 * arrets, et la puissance qui l'interesse est celle du meilleur point de la
 * station. Le detail des points de charge n'est donc pas conserve, seul leur
 * nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_stations', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('name');
            $table->string('network')->nullable();
            $table->string('operator')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->decimal('max_power_kw', 6, 1)->default(0);
            $table->unsignedSmallInteger('points_count')->default(1);
            $table->boolean('has_ccs')->default(false);
            $table->boolean('has_type2')->default(false);
            $table->boolean('has_chademo')->default(false);
            $table->boolean('is_free')->default(false);
            // Un point sur dix est en acces reserve (flotte, copropriete) :
            // inutile d'y router.
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            // Le planificateur commence toujours par une fenetre geographique,
            // filtree ensuite sur la puissance.
            $table->index(['lat', 'lon']);
            $table->index('max_power_kw');
            $table->index('network');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_stations');
    }
};
