<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor payout destinations become per-rail: a Dwolla funding source for US
 * ACH, a Paystack recipient for Nigeria, and so on. This replaces the single
 * vendors.dwolla_funding_source_id column — its data is migrated into a
 * rail='dwolla' row first, then the column is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('rail');                       // dwolla | paystack | ...
            $table->string('recipient_ref')->nullable();  // provider destination id (funding source / RCP_…)
            $table->json('details')->nullable();          // display-only: bank name, masked account
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'rail']);
        });

        // Migrate existing Dwolla destinations into the new table.
        if (Schema::hasColumn('vendors', 'dwolla_funding_source_id')) {
            $now = now();
            DB::table('vendors')
                ->whereNotNull('dwolla_funding_source_id')
                ->orderBy('id')
                ->each(function ($vendor) use ($now) {
                    DB::table('vendor_payout_methods')->insert([
                        'vendor_id' => $vendor->id,
                        'rail' => 'dwolla',
                        'recipient_ref' => $vendor->dwolla_funding_source_id,
                        'details' => null,
                        'verified_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });

            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('dwolla_funding_source_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'dwolla_funding_source_id')) {
                $table->string('dwolla_funding_source_id')->nullable()->after('email');
            }
        });

        // Restore Dwolla destinations onto the column.
        DB::table('vendor_payout_methods')->where('rail', 'dwolla')->orderBy('id')->each(function ($m) {
            DB::table('vendors')->where('id', $m->vendor_id)
                ->update(['dwolla_funding_source_id' => $m->recipient_ref]);
        });

        Schema::dropIfExists('vendor_payout_methods');
    }
};
