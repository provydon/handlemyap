<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user autonomy dial: deterministic conditions under which an invoice pays
 * itself without a human tap. Evaluated by App\Services\Payables\RuleEngine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->decimal('max_amount', 15, 2)->nullable();      // auto-approve only at/below this
            $table->boolean('known_vendors_only')->default(true);  // vendor must already exist
            $table->boolean('block_duplicates')->default(true);
            $table->json('allowed_currencies')->nullable();        // e.g. ["USD","EUR"]
            $table->decimal('daily_cap', 15, 2)->nullable();       // max auto-approved per day
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_approval_rules');
    }
};
