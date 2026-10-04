<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adapte la table users heritee du squelette Laravel a une authentification
 * externe, et accueille les identifiants SMS propres a chacun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable a l'origine ; un mot de passe est defini a la creation du
            // compte par un administrateur.
            $table->string('password')->nullable()->change();

            // Compte Free Mobile de l'utilisateur, pour ses propres alertes de
            // charge. Sans identifiants, pas de SMS : il n'y a volontairement
            // pas de repli sur un compte commun, une recharge ne doit pas faire
            // sonner le telephone de quelqu'un d'autre.
            $table->string('free_mobile_user')->nullable();
            $table->text('free_mobile_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['free_mobile_user', 'free_mobile_password']);
        });
    }
};
