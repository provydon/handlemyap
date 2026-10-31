<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the payout record rail-neutral: the old PayPal-specific column names
 * become generic provider references. Renames preserve existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->renameColumn('paypal_batch_id', 'provider_ref');
            $table->renameColumn('paypal_item_id', 'provider_item_ref');
            $table->renameColumn('paypal_status', 'provider_status');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->renameColumn('provider_ref', 'paypal_batch_id');
            $table->renameColumn('provider_item_ref', 'paypal_item_id');
            $table->renameColumn('provider_status', 'paypal_status');
        });
    }
};
