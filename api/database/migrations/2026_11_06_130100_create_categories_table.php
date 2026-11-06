<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // null = default category, visible to every user
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // stable identifier for default categories (matches categoryColors keys in the app theme)
            $table->string('key', 32)->nullable();
            $table->string('name', 64);
            $table->char('color', 7);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
