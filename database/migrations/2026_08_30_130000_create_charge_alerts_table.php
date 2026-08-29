<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            // Debut de la session de recharge, tel que reconstitue par la
            // telemetrie : c'est lui qui delimite "une charge".
            $table->dateTime('session_started_at');
            $table->unsignedTinyInteger('threshold');
            $table->decimal('soc', 5, 1)->nullable();
            $table->boolean('delivered')->default(false);
            $table->timestamps();

            // Garde-fou : un seuil ne peut etre notifie qu'une fois par charge,
            // meme si la commande est relancee ou qu'un releve est rejoue.
            $table->unique(['vehicle_id', 'session_started_at', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_alerts');
    }
};
