<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the now-unused PayPal connection columns. The app moved off PayPal rails
 * entirely; these have had no reader since. Non-destructive to other data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'paypal_client_id', 'paypal_secret', 'paypal_mode',
                'paypal_email', 'paypal_merchant_id', 'paypal_name', 'paypal_connected_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('paypal_client_id')->nullable();
            $table->text('paypal_secret')->nullable();
            $table->string('paypal_mode', 10)->default('sandbox');
            $table->string('paypal_email')->nullable();
            $table->string('paypal_merchant_id')->nullable();
            $table->string('paypal_name')->nullable();
            $table->timestamp('paypal_connected_at')->nullable();
        });
    }
};
