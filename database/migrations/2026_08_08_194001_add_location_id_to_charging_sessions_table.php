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
            $table->unsignedBigInteger('location_id')->nullable()->after('vehicle_id');
        });

        // Meme logique que pour vehicle_id : rattacher les recharges deja
        // saisies a une localisation placeholder plutot que perdre les
        // donnees. A corriger ensuite via /recharges/{id}/edit si besoin.
        if (DB::table('charging_sessions')->whereNull('location_id')->exists()) {
            $placeholderId = DB::table('locations')->value('id');

            if (! $placeholderId) {
                $placeholderId = DB::table('locations')->insertGetId([
                    'name' => 'Non renseigné',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('charging_sessions')->whereNull('location_id')->update(['location_id' => $placeholderId]);
        }

        DB::statement('ALTER TABLE charging_sessions MODIFY location_id BIGINT UNSIGNED NOT NULL');

        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('locations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charging_sessions', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
