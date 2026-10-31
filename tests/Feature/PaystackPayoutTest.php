<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end over the live Paystack rail (HTTP faked): approving a Nigerian
 * vendor's invoice sends a Paystack transfer and records the rail on the payout.
 */
class PaystackPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Leave the offline-fake rail and use the real resolver with a Paystack secret.
        config(['handlemyap.gateway' => 'auto', 'handlemyap.paystack.secret' => 'sk_test_x']);
    }

    private function nigerianInvoice(): Invoice
    {
        $user = User::factory()->create();
        $vendor = $user->vendors()->create([
            'name' => 'Lagos Supplies', 'email' => 'pay@lagos.ng', 'country' => 'NG', 'currency' => 'NGN',
        ]);
        $vendor->payoutMethods()->create(['rail' => 'paystack', 'recipient_ref' => 'RCP_1']);

        return $user->invoices()->create([
            'status' => Invoice::STATUS_NEEDS_REVIEW,
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name,
            'amount' => 150000,
            'currency' => 'NGN',
            'due_date' => now()->toDateString(),
            'source' => 'api',
        ]);
    }

    public function test_approving_a_nigerian_invoice_sends_a_paystack_transfer(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'transfer_code' => 'TRF_abc'],
            ]),
        ]);

        $invoice = $this->nigerianInvoice();

        $payout = app(PayablesOrchestrator::class)->approve($invoice);

        $this->assertNotNull($payout);
        $this->assertSame('paystack', $payout->rail);
        $this->assertSame('TRF_abc', $payout->provider_ref);
        $this->assertSame('paid', $payout->status);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/transfer'
            && $request['recipient'] === 'RCP_1'
            && $request['amount'] === 15000000 // kobo
        );
    }

    public function test_vendor_without_a_paystack_recipient_is_not_paid(): void
    {
        $user = User::factory()->create();
        $vendor = $user->vendors()->create([
            'name' => 'No Bank Ltd', 'email' => 'nb@lagos.ng', 'country' => 'NG', 'currency' => 'NGN',
        ]);
        $invoice = $user->invoices()->create([
            'status' => Invoice::STATUS_NEEDS_REVIEW, 'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name, 'amount' => 5000, 'currency' => 'NGN',
            'due_date' => now()->toDateString(), 'source' => 'api',
        ]);

        $payout = app(PayablesOrchestrator::class)->approve($invoice);

        $this->assertNotNull($payout);           // a payout row is created…
        $this->assertSame('failed', $payout->status); // …but it fails: no destination
        $this->assertSame(Invoice::STATUS_FAILED, $invoice->refresh()->status);
    }
}
