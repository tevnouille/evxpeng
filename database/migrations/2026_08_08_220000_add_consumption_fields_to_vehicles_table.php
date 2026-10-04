<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('kwh_per_100km', 6, 2)->nullable()->after('is_default');
            $table->decimal('essence_l_per_100km', 6, 2)->nullable()->after('kwh_per_100km');
            $table->decimal('diesel_l_per_100km', 6, 2)->nullable()->after('essence_l_per_100km');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['kwh_per_100km', 'essence_l_per_100km', 'diesel_l_per_100km']);
        });
    }
};
