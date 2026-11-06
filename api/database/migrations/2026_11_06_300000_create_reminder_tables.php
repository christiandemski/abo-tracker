<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('offsets_days');
            $table->boolean('push')->default(true);
            $table->boolean('email')->default(true);
            // "Abbuchung morgen" for subscriptions charged less often than monthly
            $table->boolean('charge_reminders')->default(false);
            $table->timestamps();
        });

        // Log of every reminder; the unique index makes double cron runs harmless.
        Schema::create('sent_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->date('due_date');
            $table->unsignedSmallInteger('offset_days');
            $table->string('channel', 8);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['subscription_id', 'type', 'due_date', 'offset_days', 'channel'], 'sent_reminders_dedup');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('expo_token')->unique();
            $table->string('device_name', 100)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('sent_reminders');
        Schema::dropIfExists('reminder_settings');
    }
};
