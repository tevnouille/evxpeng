<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->decimal('real_cost', 8, 2)->nullable()->after('total_cost');
        });

        // Reprise de l'existant : le cout reel part du cout facture, chaque
        // utilisateur ajuste ensuite a la main (recharge gratuite, non debitee...).
        DB::table('charging_sessions')->update(['real_cost' => DB::raw('total_cost')]);
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropColumn('real_cost');
        });
    }
};
