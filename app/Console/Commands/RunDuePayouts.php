<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Console\Command;

/**
 * Pays invoices that were approved-and-scheduled once their due date arrives.
 */
class RunDuePayouts extends Command
{
    protected $signature = 'handlemyap:run-due';

    protected $description = 'Pay approved invoices whose due date has arrived';

    public function handle(PayablesOrchestrator $orchestrator): int
    {
        $due = Invoice::query()
            ->where('status', Invoice::STATUS_SCHEDULED)
            ->whereDate('due_date', '<=', today())
            ->get();

        foreach ($due as $invoice) {
            $orchestrator->pay($invoice);
        }

        $this->info("Paid {$due->count()} due invoice(s).");

        return self::SUCCESS;
    }
}
