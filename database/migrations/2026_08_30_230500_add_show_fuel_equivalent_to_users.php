<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Affiche par defaut : c'est le comportement que tout le monde
            // connaissait avant que le reglage existe.
            $table->boolean('show_fuel_equivalent')->default(true)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_fuel_equivalent');
        });
    }
};
