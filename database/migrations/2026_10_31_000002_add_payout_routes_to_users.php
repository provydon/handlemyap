<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business's per-country rail choice: { "US": "dwolla", "NG": "paystack" }.
 * Only consulted where a country exposes more than one rail; otherwise the single
 * available rail is used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('payout_routes')->nullable()->after('default_currency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('payout_routes');
        });
    }
};
