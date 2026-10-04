<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remise consentie sur une recharge, deduite du total facture.
 *
 * Elle ne se confond ni avec le cout reel — ce que la recharge vaut — ni avec
 * un total saisi a la main : une remise plafonnee (une heure de charge chez
 * certains enseignes, le reste au tarif plein) se lit en clair, la ou un total
 * corrige a la main ne dirait pas pourquoi il est plus bas.
 *
 * Aucune reprise de l'existant : sur les recharges deja saisies, une remise
 * eventuelle est deja fondue dans le total, et NULL dit « on ne sait pas ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->decimal('discount', 8, 2)->nullable()->after('extra_cost');
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
