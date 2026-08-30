<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trajets favoris : un parcours nomme, et les bornes qu'on a retenues dessus.
 *
 * Le trace est memorise avec le trajet plutot que recalcule a chaque affichage :
 * OSRM est un service public gratuit, inutile de le solliciter pour redessiner
 * une ligne qui ne bougera pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('from_label');
            $table->decimal('from_lat', 10, 7);
            $table->decimal('from_lon', 10, 7);
            $table->string('to_label');
            $table->decimal('to_lat', 10, 7);
            $table->decimal('to_lon', 10, 7);
            $table->decimal('min_power_kw', 6, 1)->default(50);
            $table->decimal('max_detour_km', 5, 1)->default(5);
            $table->decimal('distance_km', 8, 1)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->longText('geometry')->nullable();
            $table->timestamps();
        });

        Schema::create('favorite_route_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('favorite_route_id')->constrained()->cascadeOnDelete();
            // La borne est copiee, pas seulement referencee : l'import IRVE
            // hebdomadaire supprime les stations disparues du fichier, et un
            // favori ne doit pas s'evaporer avec elles.
            $table->foreignId('charging_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('network')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->decimal('power_kw', 6, 1)->default(0);
            $table->decimal('km', 8, 1)->nullable();
            $table->timestamps();

            // Nom explicite : celui genere par defaut depasse les 64
            // caracteres autorises par MySQL pour un identifiant.
            $table->unique(['favorite_route_id', 'charging_station_id'], 'favorite_route_station_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_route_stations');
        Schema::dropIfExists('favorite_routes');
    }
};
