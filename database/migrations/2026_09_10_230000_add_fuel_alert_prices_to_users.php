<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Par utilisateur et non global (services.charge_alerts.thresholds) :
            // le prix du carburant est une donnee partagee, mais le seuil qui
            // interesse depend de qui regarde — meme choix que les identifiants
            // Free Mobile, deja propres a chaque compte. Nul par defaut : aucun
            // prix "raisonnable" ne se devine, l'alerte reste eteinte tant que
            // l'utilisateur n'a pas choisi son seuil dans /mon-compte.
            $table->decimal('fuel_alert_essence_price', 5, 3)->nullable()->after('show_fuel_equivalent');
            $table->decimal('fuel_alert_diesel_price', 5, 3)->nullable()->after('fuel_alert_essence_price');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fuel_alert_essence_price', 'fuel_alert_diesel_price']);
        });
    }
};
