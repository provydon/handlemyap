<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payee — the person or business we send money to (a contractor, a notary,
 * a supplier). `email` is the PayPal payout destination; `external_ref` lets an
 * integrating app (e.g. NotaryDash) store its own id for the payee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();            // PayPal payout receiver
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('external_ref')->nullable();      // integrator's own payee id
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'external_ref']);
            $table->index(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
