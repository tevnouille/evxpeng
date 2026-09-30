<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue un declenchement manuel (bouton InfoCar) d'un declenchement
 * automatique (App\Console\Commands\AutoOuvrirPortail, approche du
 * domicile) -- le journal disait deja quand une porte avait ete actionnee,
 * il dit maintenant aussi par quoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meross_actions', function (Blueprint $table) {
            $table->string('source')->default('manuel')->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('meross_actions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
