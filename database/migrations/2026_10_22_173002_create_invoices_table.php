<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invoice/bill to be paid. Arrives by upload, API, or MCP in any format; the
 * AI extractor normalises the messy source into the structured fields below.
 * The lifecycle runs through `status` (see App\Models\Invoice constants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();

            $table->string('source')->default('upload');     // upload | api | mcp | email
            $table->string('status')->default('received')->index();

            // Raw source
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();

            // Normalised (AI-extracted) fields
            $table->string('vendor_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('po_ref')->nullable();
            $table->json('line_items')->nullable();

            // Extraction quality + routing
            $table->unsignedSmallInteger('confidence')->nullable(); // 0-100
            $table->json('review_reasons')->nullable();

            // Integration + audit
            $table->string('external_ref')->nullable();        // integrator's invoice/job id
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'invoice_number']);
            $table->index(['user_id', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
