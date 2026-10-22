<?php

namespace App\Services\Extraction;

use App\Models\Invoice;

/**
 * Turns a raw invoice (any format, any layout) into normalised fields. This is
 * the one place AI genuinely earns its keep — reading messy, heterogeneous
 * documents. Everything downstream (rules, payouts) is deterministic.
 *
 * @return array{
 *     vendor_name?: ?string, vendor_email?: ?string, invoice_number?: ?string,
 *     amount?: ?float, currency?: ?string, issue_date?: ?string, due_date?: ?string,
 *     po_ref?: ?string, line_items?: ?array, confidence: int
 * }
 */
interface InvoiceExtractor
{
    /** @return array<string, mixed> */
    public function extract(Invoice $invoice): array;
}
