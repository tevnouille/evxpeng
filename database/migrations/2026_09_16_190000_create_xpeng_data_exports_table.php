<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xpeng_data_exports', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('requested_at');
            // DataFileExporting | ok | echec. Distinct de HTTP : l'API repond
            // 200 avec un code metier a part, meme pour signaler une erreur.
            $table->string('statut');
            $table->text('reponse_brute')->nullable();
            $table->string('chemin_fichier')->nullable();
            $table->text('erreur')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xpeng_data_exports');
    }
};
