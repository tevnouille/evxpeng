<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Token utilisateur ABRP : il identifie le couple compte + vehicule,
            // il est donc propre a chaque voiture (la cle API, elle, est globale
            // et vit dans le .env).
            $table->string('abrp_token')->nullable()->after('charging_curve');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('abrp_token');
        });
    }
};
