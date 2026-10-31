<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaidConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function configurePlaid(): void
    {
        config([
            'handlemyap.plaid.client_id' => 'pid',
            'handlemyap.plaid.secret' => 'psecret',
            'handlemyap.plaid.env' => 'sandbox',
            'handlemyap.dwolla.key' => 'dk',
            'handlemyap.dwolla.secret' => 'ds',
            'handlemyap.dwolla.env' => 'sandbox',
        ]);
    }

    public function test_link_token_endpoint_returns_a_token(): void
    {
        $this->configurePlaid();
        Http::fake(['*/link/token/create' => Http::response(['link_token' => 'link-sandbox-abc'])]);

        $this->actingAs(User::factory()->create())
            ->postJson('/settings/bank/plaid/link-token')
            ->assertOk()->assertJson(['link_token' => 'link-sandbox-abc']);
    }

    public function test_link_token_fails_gracefully_when_plaid_not_configured(): void
    {
        config(['handlemyap.plaid.client_id' => null, 'handlemyap.plaid.secret' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson('/settings/bank/plaid/link-token')
            ->assertStatus(422);
    }

    public function test_plaid_connect_creates_a_verified_source_the_user_can_send_from(): void
    {
        $this->configurePlaid();

        Http::fake([
            // Plaid
            '*/item/public_token/exchange' => Http::response(['access_token' => 'access-sandbox-xyz']),
            '*/processor/token/create' => Http::response(['processor_token' => 'processor-sandbox-xyz']),
            // Dwolla
            '*/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/funding-sources' => Http::response(null, 201, [
                'Location' => 'https://api-sandbox.dwolla.com/funding-sources/fs-verified-1',
            ]),
            '*/customers' => Http::response(null, 201, [
                'Location' => 'https://api-sandbox.dwolla.com/customers/cust-1',
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/bank/plaid/connect', [
            'public_token' => 'public-sandbox-abc',
            'account_id' => 'acct-1',
            'bank_name' => 'Chase — Operating',
        ])->assertOk()->assertJson(['connected' => true, 'bank_name' => 'Chase — Operating']);

        $user->refresh();
        $this->assertTrue($user->dwollaConnected());
        $this->assertSame('fs-verified-1', $user->dwolla_funding_source_id);
        $this->assertSame('cust-1', $user->dwolla_customer_id);

        // The processor token (not raw bank numbers) was sent to Dwolla.
        Http::assertSent(fn ($req) => str_contains($req->url(), '/funding-sources')
            && ($req['plaidToken'] ?? null) === 'processor-sandbox-xyz');
    }

    public function test_plaid_connect_reports_a_plaid_failure(): void
    {
        $this->configurePlaid();
        Http::fake(['*/item/public_token/exchange' => Http::response([], 400)]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/bank/plaid/connect', [
            'public_token' => 'bad', 'account_id' => 'acct-1', 'bank_name' => 'Bank',
        ])->assertStatus(422);

        $this->assertFalse($user->refresh()->dwollaConnected());
    }
}
