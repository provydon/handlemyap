<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Payables\PayablesOrchestrator;
use App\Services\Payouts\DwollaGateway;
use App\Services\Payouts\PayoutGatewayResolver;
use App\Services\Payouts\UnconnectedPayoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DwollaConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDwollaOnboarding(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/funding-sources' => Http::response(null, 201, [
                'Location' => 'https://api-sandbox.dwolla.com/funding-sources/fs-123',
            ]),
            '*/customers' => Http::response(null, 201, [
                'Location' => 'https://api-sandbox.dwolla.com/customers/cust-123',
            ]),
        ]);
    }

    private function bank(): array
    {
        return [
            'bank_name' => 'Acme Operating',
            'routing_number' => '222222226',
            'account_number' => '1234567890',
            'account_type' => 'checking',
        ];
    }

    public function test_connecting_bank_creates_funding_source_and_stores_ids(): void
    {
        $this->fakeDwollaOnboarding();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/bank/connect', $this->bank())->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->dwollaConnected());
        $this->assertSame('fs-123', $user->dwolla_funding_source_id);
        $this->assertSame('cust-123', $user->dwolla_customer_id);
        $this->assertSame('Acme Operating', $user->dwolla_bank_name);
    }

    public function test_connect_and_disconnect_over_json_without_reload(): void
    {
        $this->fakeDwollaOnboarding();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/bank/connect', $this->bank())
            ->assertOk()->assertJson(['connected' => true, 'bank_name' => 'Acme Operating']);

        $this->assertTrue($user->refresh()->dwollaConnected());

        $this->actingAs($user)->postJson('/settings/bank/disconnect')
            ->assertOk()->assertJson(['connected' => false]);

        $this->assertFalse($user->refresh()->dwollaConnected());
    }

    public function test_connect_over_json_rejects_bad_bank(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/customers' => Http::response(null, 201, [
                'Location' => 'https://api-sandbox.dwolla.com/customers/cust-123',
            ]),
            '*/funding-sources' => Http::response([
                '_embedded' => ['errors' => [['message' => 'Invalid routing number.']]],
            ], 400),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/bank/connect', $this->bank())
            ->assertStatus(422)->assertJsonValidationErrors('routing_number');

        $this->assertFalse($user->refresh()->dwollaConnected());
    }

    public function test_connecting_validates_input(): void
    {
        $user = User::factory()->create();

        // Routing number must be 9 digits.
        $this->actingAs($user)->post('/settings/bank/connect', [
            'bank_name' => 'Bad Bank', 'routing_number' => '123', 'account_number' => '1', 'account_type' => 'checking',
        ])->assertSessionHasErrors('routing_number');

        $this->assertFalse($user->refresh()->dwollaConnected());
    }

    public function test_resolver_is_per_user(): void
    {
        // Platform creds present, but NO default source funding source.
        config([
            'handlemyap.gateway' => 'auto',
            'handlemyap.dwolla.key' => 'k', 'handlemyap.dwolla.secret' => 's',
            'handlemyap.dwolla.env' => 'sandbox', 'handlemyap.dwolla.source_funding_source' => null,
        ]);
        $resolver = app(PayoutGatewayResolver::class);

        $unconnected = User::factory()->create(); // no bank → nothing to pay from
        $this->assertInstanceOf(UnconnectedPayoutGateway::class, $resolver->for($unconnected));

        $connected = User::factory()->create(['dwolla_funding_source_id' => 'fs-123']);
        $this->assertInstanceOf(DwollaGateway::class, $resolver->for($connected));
    }

    public function test_unconnected_user_invoice_is_parked_not_paid(): void
    {
        config(['handlemyap.gateway' => 'auto']); // no dwolla creds → unconnected
        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Acme', 'email' => 'a@acme.test', 'currency' => 'USD']);
        $invoice = $user->invoices()->create([
            'status' => Invoice::STATUS_APPROVED, 'vendor_id' => $vendor->id,
            'amount' => 100, 'currency' => 'USD',
        ]);
        $invoice->setRelation('vendor', $vendor);

        app(PayablesOrchestrator::class)->pay($invoice);

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_NEEDS_REVIEW, $invoice->status);
        $this->assertStringContainsString('Connect a bank', implode(' ', $invoice->review_reasons ?? []));
        $this->assertSame(0, $invoice->payouts()->count());
    }
}
