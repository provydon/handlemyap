<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads a received invoice with the AI extractor, then runs it through the rule
 * engine — all off the request cycle. The upload endpoint stores the file and
 * returns instantly; this job does the (potentially slow) AI read in the
 * background so the user never waits on it.
 */
class ExtractInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $invoiceId) {}

    public function handle(PayablesOrchestrator $orchestrator): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if ($invoice) {
            $orchestrator->processExtraction($invoice); // idempotent — no-op if already read
        }
    }
}
