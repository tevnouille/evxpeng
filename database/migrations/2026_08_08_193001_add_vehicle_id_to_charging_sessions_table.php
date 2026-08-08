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
            $table->unsignedBigInteger('vehicle_id')->nullable()->after('id');
        });

        // Si des recharges existent deja (saisies avant l'ajout du champ
        // vehicule), on les rattache a un vehicule par defaut plutot que de
        // perdre les donnees - l'utilisateur pourra corriger via /admin puis
        // /recharges/{id}/edit si besoin.
        if (DB::table('charging_sessions')->whereNull('vehicle_id')->exists()) {
            $defaultVehicleId = DB::table('vehicles')->value('id');

            if (! $defaultVehicleId) {
                $defaultVehicleId = DB::table('vehicles')->insertGetId([
                    'name' => 'Ma voiture',
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('charging_sessions')->whereNull('vehicle_id')->update(['vehicle_id' => $defaultVehicleId]);
        }

        // MODIFY + foreign() en SQL/Schema natif plutot que ->change()
        // (evite une dependance a doctrine/dbal, non installee ici).
        DB::statement('ALTER TABLE charging_sessions MODIFY vehicle_id BIGINT UNSIGNED NOT NULL');

        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn('vehicle_id');
        });
    }
};
