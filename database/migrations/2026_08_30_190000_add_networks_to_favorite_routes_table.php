<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reseaux retenus pour l'affichage des bornes d'un trajet favori.
 *
 * Colonne JSON plutot qu'une table de liaison : c'est une preference
 * d'affichage attachee au trajet, jamais interrogee autrement que par le trajet
 * lui-meme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorite_routes', function (Blueprint $table) {
            $table->json('networks')->nullable()->after('max_detour_km');
        });
    }

    public function down(): void
    {
        Schema::table('favorite_routes', function (Blueprint $table) {
            $table->dropColumn('networks');
        });
    }
};
