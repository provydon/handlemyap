<?php

namespace Tests\Feature;

use App\Models\AutoApprovalRule;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the transport-agnostic core: ingest → extract/normalise → rules →
 * pay/park. Runs against the offline Fake gateway + Stub extractor (the defaults
 * when no keys are set), so no PayPal credentials or network are needed.
 */
class PayablesFlowTest extends TestCase
{
    use RefreshDatabase;

    private function orchestrator(): PayablesOrchestrator
    {
        return app(PayablesOrchestrator::class);
    }

    public function test_structured_invoice_auto_approves_and_pays(): void
    {
        $user = User::factory()->create();
        AutoApprovalRule::factory()->for($user)->create([
            'enabled' => true, 'max_amount' => 1000, 'known_vendors_only' => false,
        ]);

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Acme LLC', 'vendor_email' => 'pay@acme.test',
            'amount' => 250.00, 'currency' => 'USD', 'invoice_number' => 'INV-1',
            'due_date' => now()->toDateString(),
        ]);

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertNotNull($invoice->vendor_id, 'vendor was auto-created & matched');
        $this->assertDatabaseHas('payouts', [
            'invoice_id' => $invoice->id, 'status' => Payout::STATUS_PAID,
        ]);
    }

    public function test_amount_over_limit_is_parked_for_review(): void
    {
        $user = User::factory()->create();
        AutoApprovalRule::factory()->for($user)->create([
            'enabled' => true, 'max_amount' => 100, 'known_vendors_only' => false,
        ]);

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Big Co', 'vendor_email' => 'ap@big.test',
            'amount' => 5000, 'currency' => 'USD', 'due_date' => now()->toDateString(),
        ]);

        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->status);
        $this->assertContains('Amount over your auto-approve limit', $invoice->review_reasons);
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_duplicate_invoice_is_flagged(): void
    {
        $user = User::factory()->create();
        AutoApprovalRule::factory()->for($user)->create([
            'enabled' => true, 'max_amount' => 10000, 'known_vendors_only' => false, 'block_duplicates' => true,
        ]);

        $first = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Dup Co', 'vendor_email' => 'd@dup.test', 'amount' => 400,
            'currency' => 'USD', 'invoice_number' => 'SAME-1', 'due_date' => now()->toDateString(),
        ]);
        $this->assertSame(Invoice::STATUS_PAID, $first->status);

        $second = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Dup Co', 'vendor_email' => 'd@dup.test', 'amount' => 400,
            'currency' => 'USD', 'invoice_number' => 'SAME-1', 'due_date' => now()->toDateString(),
            'external_ref' => 'ext-2',
        ]);
        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $second->status);
        $this->assertContains('Possible duplicate', $second->review_reasons);
    }

    public function test_manual_approval_triggers_payout(): void
    {
        $user = User::factory()->create(); // no auto-approval rule → human queue

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Hold Co', 'vendor_email' => 'h@hold.test',
            'amount' => 300, 'currency' => 'USD', 'due_date' => now()->toDateString(),
        ]);
        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->status);

        $payout = $this->orchestrator()->approve($invoice->refresh(), $user);

        $this->assertSame(Payout::STATUS_PAID, $payout->status);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
    }

    public function test_future_due_date_schedules_instead_of_paying(): void
    {
        $user = User::factory()->create();
        AutoApprovalRule::factory()->for($user)->create([
            'enabled' => true, 'max_amount' => 10000, 'known_vendors_only' => false,
        ]);

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'Future Co', 'vendor_email' => 'f@fut.test',
            'amount' => 200, 'currency' => 'USD', 'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertSame(Invoice::STATUS_SCHEDULED, $invoice->status);
        $this->assertDatabaseCount('payouts', 0);
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

    public function test_vendor_without_email_cannot_pay(): void
    {
        $user = User::factory()->create();
        AutoApprovalRule::factory()->for($user)->create([
            'enabled' => true, 'max_amount' => 10000, 'known_vendors_only' => false,
        ]);

        $invoice = $this->orchestrator()->ingestData($user, [
            'vendor_name' => 'No Email Co', 'amount' => 150, 'currency' => 'USD',
            'due_date' => now()->toDateString(),
        ]);

        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->status);
        $this->assertContains('Vendor has no payout email', $invoice->review_reasons);
    }
}
