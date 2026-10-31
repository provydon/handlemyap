<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Each merchant connects THEIR OWN PayPal — payouts send from their
            // account, not ours. Credentials are stored encrypted (text, since
            // ciphertext is long). Identity fields are filled from PayPal's OIDC
            // userinfo so we can show which account is connected.
            $table->text('paypal_client_id')->nullable()->after('default_currency');
            $table->text('paypal_secret')->nullable()->after('paypal_client_id');
            $table->string('paypal_mode', 10)->default('sandbox')->after('paypal_secret'); // sandbox | live
            $table->string('paypal_email')->nullable()->after('paypal_mode');
            $table->string('paypal_merchant_id')->nullable()->after('paypal_email');
            $table->string('paypal_name')->nullable()->after('paypal_merchant_id');
            $table->timestamp('paypal_connected_at')->nullable()->after('paypal_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'paypal_client_id', 'paypal_secret', 'paypal_mode',
                'paypal_email', 'paypal_merchant_id', 'paypal_name', 'paypal_connected_at',
            ]);
        });
    }
};
