<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le cout additionnel — stationnement, frais de connexion, penalite — n'etait
 * qu'un champ de calcul cote navigateur : il composait le total facture puis
 * disparaissait. Le conserver permet de rouvrir une recharge sans avoir a
 * redeviner ce qui separait le cout reel du montant debite.
 *
 * Aucune reprise de l'existant : sur les lignes deja saisies, ce qui a pu etre
 * ajoute au total est indiscernable du reste. NULL dit « on ne sait pas », un
 * zero pretendrait qu'il n'y en avait pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->decimal('extra_cost', 8, 2)->nullable()->after('real_cost');
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropColumn('extra_cost');
        });
    }
};
