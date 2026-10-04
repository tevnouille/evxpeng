<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identifiant du vehicule tel que XPCarData le publie sur MQTT.
 *
 * Les topics sont de la forme `vehicles/{mqtt_client_id}/data` : sans cette
 * colonne, rattacher un message a une voiture demanderait une correspondance
 * ecrite en dur, invisible depuis l'interface et fausse des qu'un second
 * vehicule apparait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('mqtt_client_id')->nullable()->after('abrp_token');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('mqtt_client_id');
        });
    }
};
