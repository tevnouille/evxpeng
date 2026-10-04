<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('essence_price', 6, 3);
            $table->decimal('diesel_price', 6, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_prices');
    }
};
