<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            // Horodatage renvoye par ABRP, et non celui de notre appel : c'est lui
            // qui dit quand la voiture a reellement remonte la mesure.
            $table->dateTime('recorded_at');
            $table->decimal('soc', 5, 1)->nullable();
            $table->boolean('is_charging')->default(false);
            $table->boolean('is_connected')->default(false);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('telemetry_type')->nullable();
            $table->timestamps();

            // On interroge l'API plus souvent que la voiture ne remonte de points :
            // sans cette contrainte on stockerait la meme mesure en boucle.
            $table->unique(['vehicle_id', 'recorded_at']);
            $table->index(['vehicle_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_telemetries');
    }
};
