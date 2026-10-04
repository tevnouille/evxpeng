<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_weather', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            // Conservees a titre indicatif : un relevé sert pour tout le
            // vehicule (voir App\Services\WeatherService), la position exacte
            // du jour n'est pas cherchee au kilometre pres.
            $table->decimal('lat', 8, 5);
            $table->decimal('lon', 8, 5);
            $table->decimal('temp_min_c', 4, 1)->nullable();
            $table->decimal('temp_max_c', 4, 1)->nullable();
            $table->decimal('temp_mean_c', 4, 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_weather');
    }
};
