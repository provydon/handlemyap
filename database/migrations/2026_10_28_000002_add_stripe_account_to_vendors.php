<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe pays a third-party vendor via a Connect Transfer to a *connected
 * account* (not an email). This stores the vendor's Stripe connected-account id
 * (acct_…); without it, the Stripe gateway can't transfer and fails loudly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('email'); // acct_…
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('stripe_account_id');
        });
    }
};
