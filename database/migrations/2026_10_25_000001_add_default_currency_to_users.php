<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The account's default currency — USD until the user changes it,
            // then the new default for forms and for invoices with no currency.
            $table->string('default_currency', 3)->default('USD')->after('handle');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_currency');
        });
    }
};
