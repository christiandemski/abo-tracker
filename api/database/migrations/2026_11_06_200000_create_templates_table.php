<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global templates without prices (prices go stale quickly).
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 64);
            $table->string('category_key', 32);
            $table->string('interval_unit', 8)->default('month');
            $table->unsignedTinyInteger('interval_count')->default(1);
            // extra search terms, space separated (e.g. "disney plus")
            $table->string('aliases')->nullable();
            $table->boolean('popular')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
