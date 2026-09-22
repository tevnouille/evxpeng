<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons OAuth de l'API FordConnect (Azure AD B2C), distincts des
 * identifiants d'application (FORD_CLIENT_ID/FORD_CLIENT_SECRET en .env).
 *
 * En base plutot qu'en .env, contrairement aux champs d'autorisation Xpeng
 * (XPENG_OPEN_ID/XPENG_ACCESS_TOKEN) : le refresh_token FordConnect tourne a
 * chaque utilisation (Ford en renvoie un nouveau a chaque rafraichissement),
 * une commande planifiee doit donc pouvoir le reecrire elle-meme -- modifier
 * .env ne suffit pas sans recreer le conteneur (CLAUDE.md), impossible a
 * declencher automatiquement depuis une commande artisan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ford_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('vin');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique('vin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ford_oauth_tokens');
    }
};
