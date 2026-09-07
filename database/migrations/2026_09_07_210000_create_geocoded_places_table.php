<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresses deja resolues pour une position, arrondie a la dizaine de metres.
 *
 * Une journee de roulage compte jusqu'a 780 releves : interroger la Base
 * Adresse Nationale pour chacun, a chaque affichage, serait abusif envers un
 * service public gratuit. La resolution n'a donc lieu qu'une fois par position,
 * et ce cache la rend definitive.
 *
 * Les echecs sont memorises eux aussi, avec un libelle vide : sans cela, une
 * position en pleine campagne — sans adresse connue — serait redemandee a
 * chaque affichage de la journee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocoded_places', function (Blueprint $table) {
            $table->id();

            // Arrondi a quatre decimales, soit environ onze metres : deux
            // releves de la meme place de parking partagent une seule adresse.
            $table->decimal('lat', 10, 4);
            $table->decimal('lon', 10, 4);

            $table->string('label')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode', 16)->nullable();

            // Distance entre le point demande et l'adresse trouvee, en metres :
            // elle dit si le libelle designe vraiment l'endroit ou la voiture
            // etait, ou la maison la plus proche a trois cents metres.
            $table->unsignedInteger('distance_m')->nullable();

            $table->timestamps();

            $table->unique(['lat', 'lon']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocoded_places');
    }
};
