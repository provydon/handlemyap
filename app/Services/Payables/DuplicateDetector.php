<?php

namespace App\Services\Payables;

use App\Models\Invoice;

/**
 * Flags an invoice that looks like one we've already seen — the classic AP
 * failure mode (paying the same bill twice). Deterministic, not AI.
 */
class DuplicateDetector
{
    public function isDuplicate(Invoice $invoice): bool
    {
        $query = Invoice::query()
            ->where('user_id', $invoice->user_id)
            ->whereKeyNot($invoice->getKey())
            ->whereNotIn('status', [Invoice::STATUS_REJECTED, Invoice::STATUS_FAILED]);

        // Strongest signal: same invoice number + same amount.
        if (filled($invoice->invoice_number) && $invoice->amount !== null) {
            return (clone $query)
                ->where('invoice_number', $invoice->invoice_number)
                ->where('amount', $invoice->amount)
                ->exists();
        }

        // Fallback: same vendor + amount + due date.
        if (filled($invoice->vendor_name) && $invoice->amount !== null && $invoice->due_date) {
            return (clone $query)
                ->where('vendor_name', $invoice->vendor_name)
                ->where('amount', $invoice->amount)
                ->whereDate('due_date', $invoice->due_date)
                ->exists();
        }

        return false;
    }
}
