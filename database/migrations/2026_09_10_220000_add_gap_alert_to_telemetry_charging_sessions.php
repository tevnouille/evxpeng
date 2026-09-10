<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_charging_sessions', function (Blueprint $table) {
            // Sur la livraison et non la simple tentative, comme ChargeAlert :
            // un envoi echoue (reseau coupe, 500 chez Free) doit pouvoir etre
            // retente si le boitier republie la meme session.
            $table->boolean('gap_alert_delivered')->default(false)->after('curve');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_charging_sessions', function (Blueprint $table) {
            $table->dropColumn('gap_alert_delivered');
        });
    }
};
