<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('session_date');
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('power_rating_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_kwh', 7, 2);
            // Durees stockees en TIME (HH:MM), suffisant pour une recharge/un
            // stationnement de moins de 24h (cas normal pour une recharge VE).
            $table->time('charge_duration')->nullable();
            $table->time('parking_duration')->nullable();
            $table->decimal('unit_cost', 6, 4)->nullable();
            $table->decimal('total_cost', 8, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('session_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_sessions');
    }
};
