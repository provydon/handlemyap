<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard balance endpoint reports one balance PER usable rail, each in
 * its own currency — so a business paying across rails sees its USD and NGN
 * balances side by side rather than a single conflated number.
 */
class PayoutBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_one_balance_per_usable_rail(): void
    {
        // Default test env is the offline fake rail → every live rail is usable.
        $user = User::factory()->create();

        $res = $this->actingAs($user)->getJson('/app/balance')->assertOk();

        // Dwolla (US/USD) and Paystack (NG/NGN) are usable; Wise is planned → skipped.
        $res->assertJsonCount(2, 'balances')
            ->assertJsonPath('balances.0.provider', 'Dwolla')
            ->assertJsonPath('balances.0.currency', 'USD')
            ->assertJsonPath('balances.0.demo', true)
            ->assertJsonPath('balances.1.provider', 'Paystack')
            ->assertJsonPath('balances.1.currency', 'NGN');
    }
}
