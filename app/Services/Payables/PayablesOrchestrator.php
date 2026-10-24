<?php

namespace App\Services\Payables;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Services\Extraction\InvoiceExtractor;
use App\Services\PayPal\PayoutGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * The transport-agnostic heart of handlemyap. The dashboard, the REST API and
 * the MCP server are all thin callers of this one service, so every surface
 * behaves identically:
 *
 *   ingest → extract/normalise → match vendor → apply rules →
 *       auto-approve (pay/schedule)  OR  park for human review
 *
 * AI lives only in the extractor. Everything here is deterministic and audit-able.
 */
class PayablesOrchestrator
{
    public function __construct(
        private readonly InvoiceExtractor $extractor,
        private readonly RuleEngine $rules,
        private readonly PayoutGateway $gateway,
    ) {}

    /** Ingest an uploaded document (dashboard / API multipart). */
    public function ingestUpload(User $user, UploadedFile $file, array $meta = []): Invoice
    {
        $invoice = $this->create($user, [
            'source' => $meta['source'] ?? 'upload',
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $file->store('invoices', config('handlemyap.uploads_disk')),
            'external_ref' => $meta['external_ref'] ?? null,
            'metadata' => $meta['metadata'] ?? null,
        ]);

        return $this->finalize($invoice, extract: true);
    }

    /**
     * Ingest already-structured data (API / MCP). `external_ref` doubles as an
     * idempotency key so an integrator (e.g. NotaryDash) can safely retry.
     *
     * @param array<string, mixed> $data
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

    /** Human (or MCP agent) approves a parked invoice → pays or schedules it. */
    public function approve(Invoice $invoice, string|User|null $actor = null): ?Payout
    {
        $invoice->loadMissing('vendor');
        $invoice->forceFill([
            'status' => Invoice::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $actor instanceof User ? $actor->email : ($actor ?? 'system'),
            'review_reasons' => null,
        ])->save();

        if ($invoice->due_date && $invoice->due_date->isFuture()) {
            $invoice->update(['status' => Invoice::STATUS_SCHEDULED]); // paid on due date

            return null;
        }

        return $this->pay($invoice);
    }

    /** Trigger the actual PayPal payout for an approved/scheduled invoice. */
    public function pay(Invoice $invoice): ?Payout
    {
        $invoice->loadMissing('vendor');

        if (! $invoice->isPayable()) {
            $invoice->update([
                'status' => Invoice::STATUS_NEEDS_REVIEW,
                'review_reasons' => ['Not payable: missing amount, currency, or vendor payout email'],
            ]);

            return null;
        }

        $payout = $invoice->payouts()->create([
            'user_id' => $invoice->user_id,
            'vendor_id' => $invoice->vendor_id,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => Payout::STATUS_PENDING,
            'scheduled_for' => now(),
        ]);
        $invoice->update(['status' => Invoice::STATUS_PAYING]);

        $result = $this->gateway->send($payout, $invoice->vendor);

        $payout->update([
            'status' => match (true) {
                $result->status === 'paid' => Payout::STATUS_PAID,
                $result->ok => Payout::STATUS_PROCESSING,
                default => Payout::STATUS_FAILED,
            },
            'paypal_batch_id' => $result->batchId,
            'paypal_item_id' => $result->itemId,
            'paypal_status' => $result->rawStatus,
            'paid_at' => $result->status === 'paid' ? now() : null,
            'failure_reason' => $result->error,
        ]);

        $invoice->update([
            'status' => match (true) {
                ! $result->ok => Invoice::STATUS_FAILED,
                $result->status === 'paid' => Invoice::STATUS_PAID,
                default => Invoice::STATUS_PAYING, // async; reconciled later
            },
        ]);

        return $payout->refresh();
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
                'currency' => $invoice->currency ?: 'USD',
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

    /** Apply the autonomy rules: pay itself, or wait for a human. */
    private function route(Invoice $invoice): void
    {
        $invoice->loadMissing('vendor');
        $eval = $this->rules->evaluate($invoice, $invoice->user->autoApprovalRule);

        if ($eval['auto_approve']) {
            $this->approve($invoice, 'auto');

            return;
        }

        $invoice->update([
            'status' => Invoice::STATUS_NEEDS_REVIEW,
            'review_reasons' => $eval['reasons'],
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
