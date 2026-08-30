<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elargit le motif d echec des SMS.
 *
 * Un appel a Free expire renvoie un message d exception qui reprend l URL
 * complete : il depassait les 255 caracteres de la colonne, l insertion
 * echouait, et l erreur SQL remontait en 500 — l envoi disparaissant au passage
 * du journal. Le motif est desormais tronque cote application, mais la colonne
 * ne doit plus pouvoir etre la cause d une page en erreur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->text('failure_reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->string('failure_reason')->nullable()->change();
        });
    }
};
