<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Payables\PayablesOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * UK payouts over both rails (HTTP faked): Wise (quote → transfer → fund) and
 * Stripe Global Payouts (OutboundPayments v2), chosen per the business's routing.
 */
class UkPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'handlemyap.gateway' => 'auto',
            'handlemyap.wise.token' => 'wise-tok',
            'handlemyap.wise.profile_id' => '111',
            'handlemyap.wise.env' => 'sandbox',
            'handlemyap.stripe.secret' => 'sk_test_x',
            'handlemyap.stripe.financial_account' => 'fa_123',
        ]);
    }

    private function ukInvoice(User $user): Invoice
    {
        $vendor = $user->vendors()->firstOrCreate(
            ['name' => 'London Ltd'],
            ['email' => 'pay@london.uk', 'country' => 'GB', 'currency' => 'GBP'],
        );

        return $user->invoices()->create([
            'status' => Invoice::STATUS_NEEDS_REVIEW,
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->name,
            'amount' => 250.00,
            'currency' => 'GBP',
            'due_date' => now()->toDateString(),
            'source' => 'api',
        ]);
    }

    public function test_wise_is_the_default_uk_rail(): void
    {
        Http::fake([
            '*/v3/profiles/*/quotes' => Http::response(['id' => 'quote-1']),
            '*/v1/transfers' => Http::response(['id' => 7788, 'status' => 'incoming_payment_waiting']),
            '*/v3/profiles/*/transfers/*/payments' => Http::response(['status' => 'COMPLETED']),
        ]);

        $user = User::factory()->create(); // no routing choice → Wise
        $vendor = $user->vendors()->create(['name' => 'London Ltd', 'email' => 'pay@london.uk', 'country' => 'GB', 'currency' => 'GBP']);
        $vendor->payoutMethods()->create(['rail' => 'wise', 'recipient_ref' => '555']);
        $invoice = $this->ukInvoice($user);

        $payout = app(PayablesOrchestrator::class)->approve($invoice);

        $this->assertSame('wise', $payout->rail);
        $this->assertSame('7788', $payout->provider_ref);
        $this->assertSame('processing', $payout->status);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/transfers')
            && ($r['targetAccount'] ?? null) === '555'
            && ($r['quoteUuid'] ?? null) === 'quote-1');
    }

    public function test_business_can_route_the_uk_through_stripe_global_payouts(): void
    {
        Http::fake([
            '*/v2/money_management/outbound_payments' => Http::response(['id' => 'obp_1', 'status' => 'processing']),
        ]);

        $user = User::factory()->create(['payout_routes' => ['GB' => 'stripe']]);
        $vendor = $user->vendors()->create(['name' => 'London Ltd', 'email' => 'pay@london.uk', 'country' => 'GB', 'currency' => 'GBP']);
        $vendor->payoutMethods()->create([
            'rail' => 'stripe', 'recipient_ref' => 'acct_1', 'details' => ['payout_method' => 'pm_1'],
        ]);
        $invoice = $this->ukInvoice($user);

        $payout = app(PayablesOrchestrator::class)->approve($invoice);

        $this->assertSame('stripe', $payout->rail);
        $this->assertSame('obp_1', $payout->provider_ref);
        $this->assertSame('processing', $payout->status);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/money_management/outbound_payments')
            && $r['amount']['value'] === 25000           // pence
            && $r['amount']['currency'] === 'gbp'
            && $r['to']['recipient'] === 'acct_1'
            && $r['to']['payout_method'] === 'pm_1');
    }
}
