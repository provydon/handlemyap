<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the background auto-pay machinery. Invoices are no longer paid
 * automatically by rules, nor routed across country rails — the only way to pay
 * is the interactive "Pay with PayPal" button. So the auto-approval rules table
 * and the per-country rail-routing column have no job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('auto_approval_rules');

        if (Schema::hasColumn('users', 'payout_routes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('payout_routes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('payout_routes')->nullable();
        });

        Schema::create('auto_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->boolean('known_vendors_only')->default(true);
            $table->boolean('block_duplicates')->default(true);
            $table->decimal('daily_cap', 12, 2)->nullable();
            $table->timestamps();
        });
    }
};
