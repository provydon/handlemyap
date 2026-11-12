<?php

namespace App\Services\Payables;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Services\Extraction\InvoiceExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The transport-agnostic heart of handlemyap. The dashboard, the REST API and
 * the MCP server are all thin callers of this one service, so every surface
 * behaves identically:
 *
 *   ingest → extract/normalise → match vendor → park for review
 *
 * Nothing is paid automatically: every invoice waits in the queue until a human
 * pays it with the "Pay with PayPal" button, which settles from the payer's own
 * PayPal and is recorded via markPaidByCheckout().
 *
 * AI lives only in the extractor. Everything here is deterministic and audit-able.
 */
class PayablesOrchestrator
{
    public function __construct(
        private readonly InvoiceExtractor $extractor,
    ) {}

    /** Ingest an uploaded document end-to-end (store → read → route) in one call. */
    public function ingestUpload(User $user, UploadedFile $file, array $meta = []): Invoice
    {
        return $this->processExtraction($this->receiveUpload($user, $file, $meta));
    }

    /**
     * Phase 1 of an upload: push the document to storage (S3) right away and
     * park it as "extracting". Returns immediately so the uploader gets an
     * instant "received" confirmation; the AI read happens in phase 2.
     */
    public function receiveUpload(User $user, UploadedFile $file, array $meta = []): Invoice
    {
        return $this->create($user, [
            'status' => Invoice::STATUS_EXTRACTING,
            'source' => $meta['source'] ?? 'upload',
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $file->store('invoices'),
            'external_ref' => $meta['external_ref'] ?? null,
            'metadata' => $meta['metadata'] ?? null,
        ]);
    }

    /** Phase 2: run AI extraction on a received upload, then route it. Idempotent. */
    public function processExtraction(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, [Invoice::STATUS_RECEIVED, Invoice::STATUS_EXTRACTING], true)) {
            return $invoice; // already read & routed
        }

        return $this->finalize($invoice, extract: true);
    }

    /**
     * Ingest already-structured data (API / MCP). `external_ref` doubles as an
     * idempotency key so an integrator (e.g. NotaryDash) can safely retry.
     *
     * @param  array<string, mixed>  $data
     */
    public function ingestData(User $user, array $data): Invoice
    {
        if (! empty($data['external_ref'])) {
            $existing = $user->invoices()->where('external_ref', $data['external_ref'])->first();
            if ($existing) {
                return $existing; // idempotent: already seen this reference
            }
        }

        $invoice = $this->create($user, [
            'source' => $data['source'] ?? 'api',
            'external_ref' => $data['external_ref'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'issue_date' => $this->date($data['issue_date'] ?? null),
            'due_date' => $this->date($data['due_date'] ?? null),
            'po_ref' => $data['po_ref'] ?? null,
            'line_items' => $data['line_items'] ?? null,
            'confidence' => $data['confidence'] ?? 100,
        ]);

        $extracted = array_filter([
            'vendor_name' => $data['vendor_name'] ?? null,
            'vendor_email' => $data['vendor_email'] ?? null,
            'currency' => $data['currency'] ?? null,
        ], fn ($v) => $v !== null);

        return $this->finalize($invoice, extract: false, extracted: $extracted);
    }

    /**
     * Record an invoice paid via the "Pay with PayPal" checkout — the payer
     * approved and captured the order in their own PayPal. The money has already
     * moved, so we just log the settled payout and mark the invoice paid. $demo
     * flags the offline path (no PayPal creds configured).
     */
    public function markPaidByCheckout(Invoice $invoice, ?string $orderId, ?string $captureId, bool $demo = false): Payout
    {
        $payout = $invoice->payouts()->create([
            'user_id' => $invoice->user_id,
            'vendor_id' => $invoice->vendor_id,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'rail' => 'paypal',
            'status' => Payout::STATUS_PAID,
            'provider_ref' => $orderId,
            'provider_item_ref' => $captureId,
            'provider_status' => $demo ? 'DEMO_CAPTURE' : 'COMPLETED',
            'scheduled_for' => now(),
            'paid_at' => now(),
        ]);

        $invoice->update([
            'status' => Invoice::STATUS_PAID,
            'approved_at' => $invoice->approved_at ?? now(),
            'review_reasons' => null,
        ]);

        Cache::forget("payout_balances:{$invoice->user_id}");

        return $payout;
    }

    /**
     * Undo a recorded payment: drop the payout(s) and return the invoice to
     * "needs review". Clears only our record — it does not refund via PayPal.
     */
    public function removePayment(Invoice $invoice): void
    {
        $invoice->payouts()->delete();

        $invoice->update([
            'status' => Invoice::STATUS_NEEDS_REVIEW,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        Cache::forget("payout_balances:{$invoice->user_id}");
    }

    public function hold(Invoice $invoice): void
    {
        $invoice->update(['status' => Invoice::STATUS_HELD]);
    }

    public function reject(Invoice $invoice): void
    {
        $invoice->update(['status' => Invoice::STATUS_REJECTED]);
    }

    // ---- internals -------------------------------------------------------

    private function create(User $user, array $attrs): Invoice
    {
        return $user->invoices()->create(['status' => Invoice::STATUS_RECEIVED, ...$attrs]);
    }

    private function finalize(Invoice $invoice, bool $extract, array $extracted = []): Invoice
    {
        if ($extract) {
            $invoice->update(['status' => Invoice::STATUS_EXTRACTING]);
            $extracted = $this->extractor->extract($invoice);
            $this->applyExtracted($invoice, $extracted);
        }

        // Fall back to the account's default currency when none was found.
        if (blank($invoice->currency)) {
            $invoice->update(['currency' => $invoice->user->default_currency ?: 'USD']);
        }

        $this->matchVendor($invoice, $extracted);
        $this->route($invoice);

        return $invoice->refresh();
    }

    private function applyExtracted(Invoice $invoice, array $d): void
    {
        $invoice->fill(array_filter([
            'vendor_name' => $d['vendor_name'] ?? null,
            'invoice_number' => $d['invoice_number'] ?? null,
            'amount' => $d['amount'] ?? null,
            'currency' => $d['currency'] ?? null,
            'issue_date' => $this->date($d['issue_date'] ?? null),
            'due_date' => $this->date($d['due_date'] ?? null),
            'po_ref' => $d['po_ref'] ?? null,
            'line_items' => $d['line_items'] ?? null,
            'confidence' => $d['confidence'] ?? null,
        ], fn ($v) => $v !== null))->save();

        // Keep the full structured read — payment details (e.g. the payout
        // email / destination), instructions and free-text notes — even where
        // there's no dedicated column, so nothing the AI found is thrown away.
        $meta = $invoice->metadata ?? [];
        if (! empty($d['payment'])) {
            $meta['payment'] = $d['payment'];
        }
        if (! empty($d['notes'])) {
            $meta['notes'] = $d['notes'];
        }
        if (! empty($meta)) {
            $invoice->update(['metadata' => $meta]);
        }
    }

    private function matchVendor(Invoice $invoice, array $extracted): void
    {
        if ($invoice->vendor_id) {
            return;
        }

        $email = $extracted['vendor_email'] ?? null;
        $name = $invoice->vendor_name ?: ($extracted['vendor_name'] ?? null);

        $vendor = null;
        if ($email) {
            $vendor = $invoice->user->vendors()->where('email', $email)->first();
        }
        if (! $vendor && $name) {
            $vendor = $invoice->user->vendors()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        }
        if (! $vendor && $name) {
            $vendor = $invoice->user->vendors()->create([
                'name' => $name,
                'email' => $email,
                'currency' => $invoice->currency ?: ($invoice->user->default_currency ?: 'USD'),
            ]);
        }

        if ($vendor) {
            if ($email && ! $vendor->email) {
                $vendor->update(['email' => $email]); // backfill payout address
            }
            $invoice->vendor()->associate($vendor)->save();
            $invoice->setRelation('vendor', $vendor);
        }
    }

    /** Every invoice waits in the queue to be paid by hand — no auto-pay. */
    private function route(Invoice $invoice): void
    {
        $invoice->update([
            'status' => Invoice::STATUS_NEEDS_REVIEW,
            'review_reasons' => null,
        ]);
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return rescue(fn () => Carbon::parse($value)->toDateString(), null, false);
    }
}
