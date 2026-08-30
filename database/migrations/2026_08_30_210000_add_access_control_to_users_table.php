<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autorisation d'acces propre a l'application.
 *
 * Le SSO passkey est partage avec les autres services du domaine : posseder un
 * passkey ne doit pas suffire a entrer ici. Un compte est cree a la premiere
 * visite mais reste en attente tant qu'il n'a pas ete autorise, ce qui laisse
 * l'administrateur decider sans avoir a toucher au systeme de passkeys commun
 * (l'en retirer couperait aussi l'acces aux autres applications).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('email_verified_at');
            $table->boolean('is_admin')->default(false)->after('approved_at');
            $table->timestamp('last_seen_at')->nullable()->after('is_admin');
        });

        // Le compte existant est celui du proprietaire : autorise et
        // administrateur, sans quoi plus personne ne pourrait entrer.
        DB::table('users')->orderBy('id')->limit(1)->update([
            'approved_at' => now(),
            'is_admin' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'is_admin', 'last_seen_at']);
        });
    }
};
