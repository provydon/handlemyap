<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payouts\PayoutGatewayResolver;
use App\Services\Payouts\UnconnectedPayoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The router picks a rail by the VENDOR'S COUNTRY (payment-method agnostic),
 * honoring the business's per-country choice where a country has several rails.
 */
class PayoutRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): PayoutGatewayResolver
    {
        return app(PayoutGatewayResolver::class);
    }

    public function test_us_vendor_routes_to_dwolla_by_currency(): void
    {
        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Acme', 'email' => 'a@a.test', 'currency' => 'USD']);

        $this->assertSame('dwolla', $this->resolver()->railFor($user, $vendor));
    }

    public function test_nigerian_vendor_routes_to_paystack_by_country(): void
    {
        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Lagos Ltd', 'email' => 'l@l.ng', 'country' => 'NG', 'currency' => 'NGN']);

        $this->assertSame('paystack', $this->resolver()->railFor($user, $vendor));
    }

    public function test_uk_vendor_defaults_to_wise_and_business_can_switch_to_stripe(): void
    {
        $vendorData = ['name' => 'London Ltd', 'email' => 'l@l.uk', 'country' => 'GB', 'currency' => 'GBP'];

        // No choice → the first available UK rail (Wise).
        $user = User::factory()->create();
        $this->assertSame('wise', $this->resolver()->railFor($user, $user->vendors()->create($vendorData)));

        // The business picks Stripe Global Payouts for the UK.
        $picker = User::factory()->create(['payout_routes' => ['GB' => 'stripe']]);
        $this->assertSame('stripe', $this->resolver()->railFor($picker, $picker->vendors()->create($vendorData)));
    }

    public function test_business_choice_wins_when_a_country_has_several_rails(): void
    {
        // Give Nigeria a second rail just for this test.
        config(['handlemyap.rails.flutterwave' => [
            'provider' => 'Flutterwave', 'method' => 'Bank transfer', 'country' => 'NG', 'currency' => 'NGN',
        ]]);

        $vendorData = ['name' => 'Lagos Ltd', 'email' => 'l@l.ng', 'country' => 'NG', 'currency' => 'NGN'];

        // No choice → first available rail (paystack).
        $user = User::factory()->create();
        $this->assertSame('paystack', $this->resolver()->railFor($user, $user->vendors()->create($vendorData)));

        // Explicit choice → that rail.
        $picker = User::factory()->create(['payout_routes' => ['NG' => 'flutterwave']]);
        $this->assertSame('flutterwave', $this->resolver()->railFor($picker, $picker->vendors()->create($vendorData)));
    }

    public function test_invalid_choice_falls_back_to_an_available_rail(): void
    {
        $user = User::factory()->create(['payout_routes' => ['NG' => 'bogus']]);
        $vendor = $user->vendors()->create(['name' => 'Lagos', 'email' => 'l@l.ng', 'country' => 'NG', 'currency' => 'NGN']);

        $this->assertSame('paystack', $this->resolver()->railFor($user, $vendor));
    }

    public function test_unsupported_country_has_no_rail_and_fails_closed(): void
    {
        // A real resolution (not the offline fake) must refuse when no rail serves the country.
        config(['handlemyap.gateway' => 'auto']);

        $user = User::factory()->create();
        $vendor = $user->vendors()->create(['name' => 'Tokyo KK', 'email' => 't@t.jp', 'country' => 'JP', 'currency' => 'JPY']);

        $this->assertNull($this->resolver()->railFor($user, $vendor));

        $resolved = $this->resolver()->resolve($user, $vendor);
        $this->assertNull($resolved['rail']);
        $this->assertInstanceOf(UnconnectedPayoutGateway::class, $resolved['gateway']);
    }
}
