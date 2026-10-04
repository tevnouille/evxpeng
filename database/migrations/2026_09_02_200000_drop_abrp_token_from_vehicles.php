<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fin du passage par A Better Routeplanner.
 *
 * ABRP n'a jamais ete une source : c'etait un relai. Tous les releves recus
 * portaient `telemetry_type = obdble`, c'est-a-dire le boitier OBD, dont les
 * donnees faisaient un detour par le cloud d'Iternio — au prix d'un
 * appauvrissement (ni tension, ni batterie 12 V, ni temperatures moteur, ni
 * compteurs d'energie) et d'un traitement par lots. Le boitier publie
 * desormais en direct sur le broker de la maison.
 *
 * Les releves deja collectes sont conserves : ils restent lisibles et gardent
 * leur source d'origine dans `telemetry_type`. Seul le jeton disparait — il se
 * regenere depuis les reglages d'ABRP si le besoin revenait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('abrp_token');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('abrp_token')->nullable()->after('charging_curve');
        });
    }
};
