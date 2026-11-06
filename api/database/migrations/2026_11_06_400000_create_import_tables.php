<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One CSV import – metadata only, the raw file is never stored.
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_format', 32);
            $table->string('file_name', 120)->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('debits_total')->default(0);
            $table->string('status', 16)->default('review');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        // Detected recurring payments. Only subscription-like groups, no IBAN/BIC.
        // Deleted after "Übernehmen", at the latest after 24 hours.
        Schema::create('import_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->string('payee_normalized', 120);
            $table->string('display_name', 100);
            $table->string('creditor_id', 35)->nullable();
            $table->string('mandate_ref', 35)->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('interval_unit', 8)->nullable();
            $table->unsignedTinyInteger('interval_count')->nullable();
            $table->unsignedSmallInteger('occurrences');
            $table->date('first_date');
            $table->date('last_date');
            $table->string('confidence', 8); // high | check
            $table->string('template_key', 48)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('new'); // new | duplicate | price_change | accepted | ignored
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index('created_at');
        });

        // Remembered mapping payee → subscription, so the next import recognizes it.
        Schema::create('import_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('creditor_id', 35)->nullable();
            $table->string('mandate_ref', 35)->nullable();
            $table->string('pattern', 120)->nullable();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'creditor_id']);
            $table->index(['user_id', 'pattern']);
        });

        Schema::table('price_changes', function (Blueprint $table) {
            // Set once the new price was written to the subscription (immediately or on effective_date).
            $table->timestamp('applied_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('price_changes', fn (Blueprint $table) => $table->dropColumn('applied_at'));
        Schema::dropIfExists('import_rules');
        Schema::dropIfExists('import_candidates');
        Schema::dropIfExists('import_batches');
    }
};
