<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des actions declenchees sur le garage/portail (App\Services\MerossClient).
 *
 * Pas de user_id : InfoCar est la seule adresse publique du site (voir son
 * docblock), l'en-tete d'identite y est toujours vide -- CurrentUser::get()
 * y vaut null. Le code a 6 chiffres est la seule barriere, pas une identite ;
 * ce journal sert a savoir quand une porte a ete actionnee, pas par qui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meross_actions', function (Blueprint $table) {
            $table->id();
            $table->string('appareil');
            $table->string('action');
            $table->boolean('reussi');
            $table->string('erreur')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meross_actions');
    }
};
