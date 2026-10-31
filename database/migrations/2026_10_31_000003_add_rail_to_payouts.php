<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which rail a payout moved over (dwolla | paystack | …). Lets reconciliation
 * poll the right provider for each payout, now that one business can pay across
 * several rails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('rail')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('rail');
        });
    }
};
