<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_changes', function (Blueprint $table) {
            $table->id();
            // Denormalized owner for the tenant scope (BelongsToUser)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('old_cents');
            $table->unsignedInteger('new_cents');
            $table->date('effective_date');
            $table->string('source', 16)->default('manual');
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->index(['subscription_id', 'effective_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_changes');
    }
};
