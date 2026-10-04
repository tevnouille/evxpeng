<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VIN du vehicule : c'est lui qui rattache les donnees constructeur a un
 * vehicule, donc a son proprietaire -- plutot qu'une liste d'adresses email,
 * comme deja pour mqtt_client_id et le boitier OBD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vin', 17)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('vin');
        });
    }
};
