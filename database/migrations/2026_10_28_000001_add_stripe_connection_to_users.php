<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Each merchant connects THEIR OWN Stripe — payouts send from their
            // account, not ours. The secret key is stored encrypted (text, since
            // ciphertext is long). Identity fields are read back from GET
            // /v1/account so we can show which account is connected.
            $table->text('stripe_secret')->nullable()->after('default_currency');
            $table->string('stripe_account_id')->nullable()->after('stripe_secret'); // acct_…
            $table->string('stripe_mode', 10)->default('test')->after('stripe_account_id'); // test | live
            $table->string('stripe_name')->nullable()->after('stripe_mode');
            $table->string('stripe_email')->nullable()->after('stripe_name');
            $table->timestamp('stripe_connected_at')->nullable()->after('stripe_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_secret', 'stripe_account_id', 'stripe_mode',
                'stripe_name', 'stripe_email', 'stripe_connected_at',
            ]);
        });
    }
};
