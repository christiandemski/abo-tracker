<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sender for cancellation letters (optional, entered by the user)
        Schema::table('users', function (Blueprint $table) {
            $table->string('address_street', 150)->nullable()->after('timezone');
            $table->string('address_zip', 10)->nullable()->after('address_street');
            $table->string('address_city', 100)->nullable()->after('address_zip');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('customer_number', 64)->nullable()->after('provider_url');
            $table->string('provider_email')->nullable()->after('customer_number');
            // "Ich habe gekündigt": when and how
            $table->date('cancelled_at')->nullable()->after('ends_at');
            $table->string('cancel_method', 16)->nullable()->after('cancelled_at');
            // "Behalten": no deadline banner/reminders until this term end
            $table->date('kept_until')->nullable()->after('cancel_method');
        });

        Schema::create('usage_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('answer', 8); // often | rare | never | skip
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['user_id', 'checked_at']);
            $table->index(['subscription_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_checks');
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn(['customer_number', 'provider_email', 'cancelled_at', 'cancel_method', 'kept_until']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['address_street', 'address_zip', 'address_city']));
    }
};
