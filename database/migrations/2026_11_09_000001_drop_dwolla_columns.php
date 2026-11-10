<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the business's Dwolla ACH funding-source columns. Payouts now run on
 * platform-funded rails (PayPal for the US, Paystack, Wise, Stripe …) where the
 * vendor's destination lives in vendor_payout_methods and there's nothing
 * per-business to connect — so these columns have no job.
 *
 * (The per-vendor vendors.dwolla_funding_source_id column was already dropped
 * when vendor_payout_methods was introduced, so only users is touched here.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dwolla_customer_id', 'dwolla_funding_source_id',
                'dwolla_bank_name', 'dwolla_connected_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dwolla_customer_id')->nullable()->after('default_currency');
            $table->string('dwolla_funding_source_id')->nullable()->after('dwolla_customer_id');
            $table->string('dwolla_bank_name')->nullable()->after('dwolla_funding_source_id');
            $table->timestamp('dwolla_connected_at')->nullable()->after('dwolla_bank_name');
        });
    }
};
