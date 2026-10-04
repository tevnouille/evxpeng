<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance d'un trajet recu par copie.
 *
 * Un simple libelle, pas une cle etrangere : la copie est independante de
 * l'original des sa creation, et doit rester lisible meme si le compte d'origine
 * disparait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorite_routes', function (Blueprint $table) {
            $table->string('copied_from')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('favorite_routes', function (Blueprint $table) {
            $table->dropColumn('copied_from');
        });
    }
};
