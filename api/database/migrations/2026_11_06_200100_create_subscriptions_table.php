<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);

            // Money always in integer cents
            $table->unsignedInteger('amount_cents');
            $table->string('interval_unit', 8);
            $table->unsignedTinyInteger('interval_count')->default(1);

            // First known charge; all later charges are computed from here (never month by month).
            $table->date('anchor_date');
            $table->date('next_charge_date')->nullable();

            // Contract term
            $table->date('start_date')->nullable();
            $table->unsignedSmallInteger('min_term_months')->nullable();
            $table->unsignedSmallInteger('renewal_months')->nullable();
            $table->unsignedSmallInteger('notice_value')->nullable();
            $table->string('notice_unit', 8)->nullable();
            $table->date('term_end_date')->nullable();
            $table->date('cancel_by_date')->nullable();

            $table->string('status', 16)->default('active');
            $table->date('ends_at')->nullable();
            $table->boolean('remind')->default(true);

            $table->string('payment_method', 100)->nullable();
            $table->string('provider_url')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('template_key', 48)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'next_charge_date']);
            $table->index(['user_id', 'cancel_by_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
