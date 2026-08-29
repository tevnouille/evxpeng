<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Slug d'un fichier de resources/data/charging-curves : pas de cle
            // etrangere, les courbes ne sont pas en base.
            $table->string('charging_curve')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('charging_curve');
        });
    }
};
