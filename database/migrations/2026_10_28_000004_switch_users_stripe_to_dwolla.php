<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Swap the (short-lived) Stripe connection columns for Dwolla ACH ones. The
 * business "connection" is now a bank funding source under the platform's one
 * Dwolla app — no per-merchant API key, so nothing secret is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_secret', 'stripe_account_id', 'stripe_mode',
                'stripe_name', 'stripe_email', 'stripe_connected_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('dwolla_customer_id')->nullable()->after('default_currency');
            $table->string('dwolla_funding_source_id')->nullable()->after('dwolla_customer_id');
            $table->string('dwolla_bank_name')->nullable()->after('dwolla_funding_source_id');
            $table->timestamp('dwolla_connected_at')->nullable()->after('dwolla_bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dwolla_customer_id', 'dwolla_funding_source_id',
                'dwolla_bank_name', 'dwolla_connected_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('stripe_secret')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->string('stripe_mode', 10)->default('test');
            $table->string('stripe_name')->nullable();
            $table->string('stripe_email')->nullable();
            $table->timestamp('stripe_connected_at')->nullable();
        });
    }
};
