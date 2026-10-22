<?php

namespace App\Services\Extraction;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

/**
 * Deterministic, offline extractor used when no AI key is configured and in
 * tests. Understands two simple invoice carriers so the whole pipeline is
 * demonstrable without any external call:
 *   - a JSON file whose keys map onto our fields, or
 *   - a plain-text invoice with "Label: value" lines (Vendor, Email, Amount…).
 *
 * The real AI extractor ({@see AiInvoiceExtractor}) swaps in transparently.
 */
class StubExtractor implements InvoiceExtractor
{
    public function extract(Invoice $invoice): array
    {
        $raw = $this->read($invoice);

        if ($raw === null) {
            return ['confidence' => 0];
        }

        // JSON invoice → high confidence, direct map.
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $this->fromArray($json) + ['confidence' => 100];
        }

        return $this->fromText($raw);
    }

    private function read(Invoice $invoice): ?string
    {
        if (! $invoice->file_path || ! Storage::exists($invoice->file_path)) {
            return null;
        }

        return Storage::get($invoice->file_path);
    }

    /** @param array<string, mixed> $data */
    private function fromArray(array $data): array
    {
        $get = fn (array $keys) => collect($keys)
            ->map(fn ($k) => $data[$k] ?? null)
            ->first(fn ($v) => $v !== null);

        return array_filter([
            'vendor_name' => $get(['vendor_name', 'vendor', 'from', 'biller']),
            'vendor_email' => $get(['vendor_email', 'email', 'pay_to_email']),
            'invoice_number' => $get(['invoice_number', 'invoice_no', 'number', 'invoice']),
            'amount' => ($a = $get(['amount', 'total', 'amount_due', 'grand_total'])) !== null ? (float) $a : null,
            'currency' => $get(['currency', 'ccy']),
            'issue_date' => $get(['issue_date', 'date', 'invoice_date']),
            'due_date' => $get(['due_date', 'due', 'payable_by']),
            'po_ref' => $get(['po_ref', 'po', 'purchase_order']),
            'line_items' => is_array($data['line_items'] ?? null) ? $data['line_items'] : null,
        ], fn ($v) => $v !== null);
    }

    private function fromText(string $text): array
    {
        $field = function (array $labels) use ($text): ?string {
            foreach ($labels as $label) {
                if (preg_match('/'.preg_quote($label, '/').'\s*[:#]?\s*(.+)/i', $text, $m)) {
                    return trim($m[1]);
                }
            }

            return null;
        };

        $amountRaw = $field(['amount due', 'total due', 'grand total', 'total', 'amount']);
        $amount = $amountRaw !== null
            ? (float) preg_replace('/[^0-9.]/', '', $amountRaw)
            : null;

        $currency = $field(['currency']);
        if (! $currency && $amountRaw && preg_match('/(USD|EUR|GBP|NGN|CAD|AUD)/i', $amountRaw, $m)) {
            $currency = strtoupper($m[1]);
        }

        $out = array_filter([
            'vendor_name' => $field(['vendor', 'from', 'bill from', 'biller', 'company']),
            'vendor_email' => $field(['email', 'pay to email', 'contact']),
            'invoice_number' => $field(['invoice number', 'invoice no', 'invoice']),
            'amount' => $amount,
            'currency' => $currency,
            'issue_date' => $field(['invoice date', 'date issued', 'date']),
            'due_date' => $field(['due date', 'payable by', 'due']),
            'po_ref' => $field(['po number', 'purchase order', 'po']),
        ], fn ($v) => $v !== null && $v !== '');

        // Confidence reflects how much we actually recognised.
        $found = count(array_intersect_key($out, array_flip(['vendor_name', 'amount', 'due_date'])));
        $out['confidence'] = match ($found) {
            3 => 85,
            2 => 65,
            1 => 45,
            default => 20,
        };

        return $out;
    }
}
