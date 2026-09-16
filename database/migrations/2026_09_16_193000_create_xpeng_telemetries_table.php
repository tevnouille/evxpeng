<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xpeng_telemetries', function (Blueprint $table) {
            $table->id();
            $table->string('vin');
            // Minute pleine (secondes a 0) : c'est la granularite de stockage
            // retenue, pas celle des fichiers Xpeng qui sont a la seconde
            // (voir App\Services\XpengExportParser) -- le detail seconde par
            // seconde n'est jamais importe, seuls les fichiers bruts le
            // conservent (storage/app/xpeng).
            $table->timestampTz('horodatage');
            // Lignes brutes agregees dans cette minute, les trois jeux de
            // donnees Xpeng confondus (pas le nombre de secondes distinctes :
            // chaque seconde contribue une ligne par jeu, donc jusqu'a x3).
            // Purement indicatif -- utile pour repérer une minute mal couverte.
            $table->unsignedSmallInteger('nb_releves');

            $table->float('vitesse_moy_kmh')->nullable();
            $table->float('vitesse_max_kmh')->nullable();
            $table->float('odometre_km')->nullable();

            $table->float('soc_moy')->nullable();
            $table->float('soc_min')->nullable();
            $table->float('soc_max')->nullable();
            $table->float('autonomie_km')->nullable();
            $table->float('puissance_charge_moy_kw')->nullable();
            $table->float('temp_batterie_max_c')->nullable();
            $table->float('temp_batterie_min_c')->nullable();

            $table->float('pression_av_gauche_kpa')->nullable();
            $table->float('pression_av_droite_kpa')->nullable();
            $table->float('pression_ar_gauche_kpa')->nullable();
            $table->float('pression_ar_droite_kpa')->nullable();

            $table->timestampsTz();

            $table->unique(['vin', 'horodatage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xpeng_telemetries');
    }
};
