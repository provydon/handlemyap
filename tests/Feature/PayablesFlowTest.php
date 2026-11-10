<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the transport-agnostic core: ingest → extract/normalise → match
 * vendor → park for review. Nothing is paid automatically; payment happens only
 * via the "Pay with PayPal" checkout (recorded by markPaidByCheckout).
 */
class PayablesFlowTest extends TestCase
{
    use RefreshDatabase;

    private function orchestrator(): PayablesOrchestrator
    {
        return app(PayablesOrchestrator::class);
    }

    public function test_structured_invoice_is_queued_for_review(): void
    {
        $user = User::factory()->create();

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Acme LLC', 'vendor_email' => 'pay@acme.test',
            'amount' => 250.00, 'currency' => 'USD', 'invoice_number' => 'INV-1',
            'due_date' => now()->toDateString(),
        ]);

        // No auto-pay: it waits in the queue to be paid by hand.
        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->status);
        $this->assertNotNull($invoice->vendor_id, 'vendor was auto-created & matched');
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_future_due_date_still_just_queues(): void
    {
        $user = User::factory()->create();

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Future Co', 'vendor_email' => 'f@fut.test',
            'amount' => 200, 'currency' => 'USD', 'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->status);
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_marking_paid_by_checkout_records_a_payout(): void
    {
        $user = User::factory()->create();

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Pay Co', 'vendor_email' => 'p@pay.test',
            'amount' => 300, 'currency' => 'USD', 'due_date' => now()->toDateString(),
        ]);
        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->status);

        $payout = $this->orchestrator()->markPaidByCheckout($invoice->refresh(), 'ORDER-1', 'CAP-1');

        $this->assertSame(Payout::STATUS_PAID, $payout->status);
        $this->assertSame('paypal', $payout->rail);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
    }

    public function test_external_ref_is_idempotent(): void
    {
        $user = User::factory()->create();
        $payload = [
            'vendor_name' => 'Idem Co', 'vendor_email' => 'i@idem.test', 'amount' => 100,
            'currency' => 'USD', 'due_date' => now()->toDateString(), 'external_ref' => 'job-777',
        ];

        $a = $this->orchestrator()->ingestData($user, $payload);
        $b = $this->orchestrator()->ingestData($user, $payload);

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_hold_and_reject_move_the_invoice_out_of_the_queue(): void
    {
        $user = User::factory()->create();
        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Park Co', 'vendor_email' => 'x@park.test', 'amount' => 90, 'currency' => 'USD',
        ]);

        $this->orchestrator()->hold($invoice);
        $this->assertSame(Invoice::STATUS_HELD, $invoice->refresh()->status);

        $this->orchestrator()->reject($invoice);
        $this->assertSame(Invoice::STATUS_REJECTED, $invoice->refresh()->status);
    }
}
