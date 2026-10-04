<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xpeng_telemetries', function (Blueprint $table) {
            // Numero de la zone de mesure (pas forcement une cellule
            // individuelle) portant la temperature max/min de la batterie,
            // pas la temperature elle-meme (deja dans temp_batterie_*_c) --
            // utile pour reperer une zone durablement plus chaude/froide que
            // les autres, signe precoce de desequilibre.
            $table->unsignedTinyInteger('cellule_temp_max_num')->nullable()->after('temp_batterie_min_c');
            $table->unsignedTinyInteger('cellule_temp_min_num')->nullable()->after('cellule_temp_max_num');
        });
    }

    public function down(): void
    {
        Schema::table('xpeng_telemetries', function (Blueprint $table) {
            $table->dropColumn(['cellule_temp_max_num', 'cellule_temp_min_num']);
        });
    }
};
