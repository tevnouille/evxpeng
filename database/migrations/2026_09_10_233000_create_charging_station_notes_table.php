<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_station_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charging_station_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();

            // Une appreciation par borne et par compte : la reecrire remplace
            // l'observation precedente, elle ne s'empile pas. Si un jour on veut
            // dater chaque passage, ce sera une autre table — celle-ci reste la
            // note "en cours" affichee dans le planificateur et les favoris.
            $table->unique(['user_id', 'charging_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_station_notes');
    }
};
