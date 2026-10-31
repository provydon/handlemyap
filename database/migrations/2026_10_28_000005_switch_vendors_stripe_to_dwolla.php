<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vendor is paid by ACH to a Dwolla funding source (their bank), not a Stripe
 * connected account. Stores the funding-source id the gateway transfers to;
 * raw bank details live only transiently during onboarding (the seam), never here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('stripe_account_id');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('dwolla_funding_source_id')->nullable()->after('email'); // bank destination
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('dwolla_funding_source_id');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('email');
        });
    }
};
