<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recharges detectees que l'utilisateur ne veut plus se voir proposer.
 *
 * Le rapprochement automatique ne reconnait qu'une saisie issue d'une detection
 * (charging_sessions.telemetry_started_at). Une recharge saisie a la main reste
 * donc proposee indefiniment, alors qu'elle est deja enregistree. Cette table
 * porte ce « non, celle-la, c'est reglé » — sans supprimer la detection
 * elle-meme, qui reste visible sur Ma voiture et peut etre retablie.
 *
 * Marqueur temporel plutot que cle etrangere vers un releve : la detection est
 * recalculee a chaque affichage, elle n'a pas d'identite en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ignored_telemetry_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->timestamps();

            // Le debut de session identifie la detection : deux clics sur le
            // meme bouton ne doivent pas creer deux lignes.
            $table->unique(['vehicle_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ignored_telemetry_charges');
    }
};
