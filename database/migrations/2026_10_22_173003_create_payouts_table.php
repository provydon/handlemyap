<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A money movement: the actual PayPal Payout triggered for an approved invoice.
 * Holds PayPal's batch/item ids and the settlement status we poll/receive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending')->index(); // pending|processing|paid|failed

            $table->string('paypal_batch_id')->nullable();
            $table->string('paypal_item_id')->nullable();
            $table->string('paypal_status')->nullable();

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
