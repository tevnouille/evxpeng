<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->text('message');
            $table->boolean('delivered')->default(false);
            // Code renvoye par l'API Free, nul si l'appel n'a meme pas abouti.
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
