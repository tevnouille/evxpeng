<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acces explicite a « Donnees Xpeng », accorde par l'administrateur -- comme
 * is_admin/approved_at, pas une liste d'emails dans le code. Le vehicule Xpeng
 * n'appartient pas forcement au compte qu'on veut y autoriser : aucune
 * relation de possession naturelle a exploiter ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('xpeng_access')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('xpeng_access');
        });
    }
};
