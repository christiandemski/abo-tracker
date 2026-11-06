<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // SHA-256 of the secret token; the token itself is never stored. Null = link switched off.
            $table->char('token_hash', 64)->nullable()->unique();
            $table->json('options')->nullable();
            $table->timestamp('token_created_at')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feeds');
    }
};
