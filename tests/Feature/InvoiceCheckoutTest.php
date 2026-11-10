<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The interactive "Pay with PayPal" checkout: start → approve at PayPal →
 * return/capture. Runs in demo mode with no creds, and against a faked PayPal
 * when the app is configured.
 */
class InvoiceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceFor(User $user, array $overrides = []): Invoice
    {
        $vendor = $user->vendors()->create(['name' => 'Acme Supplies', 'email' => 'ap@acme.test', 'currency' => 'USD']);

        return $user->invoices()->create([
            'vendor_id' => $vendor->id,
            'source' => 'manual',
            'status' => Invoice::STATUS_NEEDS_REVIEW,
            'amount' => 120.00,
            'currency' => 'USD',
            ...$overrides,
        ]);
    }

    public function test_demo_mode_pays_the_invoice_without_paypal_creds(): void
    {
        // The test env forces gateway=fake → checkout runs in demo mode (no network).
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $this->actingAs($user)
            ->post("/app/invoices/{$invoice->id}/pay/paypal")
            ->assertRedirect(route('dashboard'));

        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertDatabaseHas('payouts', [
            'invoice_id' => $invoice->id,
            'rail' => 'paypal',
            'status' => 'paid',
            'provider_status' => 'DEMO_CAPTURE',
        ]);
    }

    public function test_configured_checkout_redirects_to_paypal_then_captures(): void
    {
        config([
            'handlemyap.gateway' => 'auto', // leave demo/fake mode so a real order is created
            'handlemyap.paypal.client_id' => 'id',
            'handlemyap.paypal.secret' => 'sec',
            'handlemyap.paypal.env' => 'sandbox',
        ]);

        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        // 1) Start → create an order, redirect the browser to PayPal's approve URL.
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-1',
                'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1']],
            ], 201),
        ]);

        $this->actingAs($user)
            ->post("/app/invoices/{$invoice->id}/pay/paypal")
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');

        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->refresh()->status); // not paid until capture

        // 2) Return from PayPal → capture → invoice paid.
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v2/checkout/orders/ORDER-1/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1']]]]],
            ], 201),
        ]);

        $this->actingAs($user)
            ->get(route('invoice.pay.return', $invoice).'?token=ORDER-1')
            ->assertRedirect(route('dashboard'));

        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertDatabaseHas('payouts', [
            'invoice_id' => $invoice->id,
            'rail' => 'paypal',
            'status' => 'paid',
            'provider_ref' => 'ORDER-1',
            'provider_item_ref' => 'CAP-1',
        ]);
    }

    public function test_popup_order_and_capture_endpoints_pay_the_invoice(): void
    {
        config([
            'handlemyap.gateway' => 'auto',
            'handlemyap.paypal.client_id' => 'id',
            'handlemyap.paypal.secret' => 'sec',
        ]);

        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        // SDK createOrder → our /order endpoint returns the order id.
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v2/checkout/orders' => Http::response(['id' => 'ORDER-9', 'links' => [['rel' => 'payer-action', 'href' => 'https://x']]], 201),
        ]);

        $this->actingAs($user)
            ->postJson("/app/invoices/{$invoice->id}/pay/paypal/order")
            ->assertOk()->assertJsonPath('id', 'ORDER-9');

        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->refresh()->status); // not paid until capture

        // SDK onApprove → our /capture endpoint captures and marks it paid.
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v2/checkout/orders/ORDER-9/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-9']]]]],
            ], 201),
        ]);

        $this->actingAs($user)
            ->postJson("/app/invoices/{$invoice->id}/pay/paypal/capture", ['order_id' => 'ORDER-9'])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertDatabaseHas('payouts', [
            'invoice_id' => $invoice->id,
            'rail' => 'paypal',
            'status' => 'paid',
            'provider_ref' => 'ORDER-9',
            'provider_item_ref' => 'CAP-9',
        ]);
    }

    public function test_cannot_pay_another_users_invoice(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->invoiceFor($owner);

        $this->actingAs(User::factory()->create())
            ->post("/app/invoices/{$invoice->id}/pay/paypal")
            ->assertForbidden();

        $this->assertNotSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
    }
}
