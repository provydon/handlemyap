<?php

namespace App\Services\Payouts;

use App\Models\User;
use App\Models\Vendor;

/**
 * Routes a payout to the right rail. The decision is driven by the VENDOR'S
 * COUNTRY (payment-method agnostic): pay a US vendor over Dwolla ACH, a Nigerian
 * vendor over Paystack, and so on. Where a country exposes more than one rail,
 * the business's choice (User::$payout_routes) wins; otherwise the single
 * available rail is used.
 *
 * Credentials are PLATFORM-level (one Dwolla app, one Paystack account for
 * everyone). What's per-business is the source of funds: Dwolla pays from the
 * business's own connected bank; Paystack pays from the platform balance.
 *
 * gateway=fake (tests / offline demo) forces the FakePayoutGateway for every
 * rail, so the whole flow runs with zero credentials.
 */
class PayoutGatewayResolver
{
    public function __construct(private readonly RailCatalog $catalog) {}

    /**
     * The rail + gateway to pay this vendor with. When $vendor is null (balance
     * / reconcile fallbacks) the business's default send rail is used.
     *
     * @return array{rail: ?string, gateway: PayoutGateway}
     */
    public function resolve(User $user, ?Vendor $vendor = null): array
    {
        if (config('handlemyap.gateway') === 'fake') {
            $rail = ($vendor ? $this->railFor($user, $vendor) : null) ?? 'dwolla';

            return ['rail' => $rail, 'gateway' => app(PayoutGateway::class)];
        }

        $rail = $vendor ? $this->railFor($user, $vendor) : $this->defaultRail($user);

        if ($rail === null) {
            $country = $vendor ? $this->countryFor($vendor) : null;
            $why = $country
                ? "No payout rail is connected for {$country} yet."
                : 'No payout rail is available — connect a bank in Settings to pay.';

            return ['rail' => null, 'gateway' => new UnconnectedPayoutGateway($why)];
        }

        return ['rail' => $rail, 'gateway' => $this->gateway($rail, $user)];
    }

    /** Convenience: just the gateway for this vendor (or the default). */
    public function for(User $user, ?Vendor $vendor = null): PayoutGateway
    {
        return $this->resolve($user, $vendor)['gateway'];
    }

    /** The rail key to pay a vendor over, honoring the business's per-country choice. */
    public function railFor(User $user, Vendor $vendor): ?string
    {
        $country = $this->countryFor($vendor);
        if ($country === null) {
            return null;
        }

        $available = array_keys($this->catalog->forCountry($country));
        if ($available === []) {
            return null;
        }

        $chosen = $user->payout_routes[$country] ?? null;
        if ($chosen && in_array($chosen, $available, true)) {
            return $chosen;
        }

        return $available[0];
    }

    /** Build the concrete gateway for a rail. In fake mode, always the Fake. */
    public function gateway(string $rail, User $user): PayoutGateway
    {
        if (config('handlemyap.gateway') === 'fake') {
            return app(PayoutGateway::class);
        }

        return match ($rail) {
            'dwolla' => $this->dwolla($user),
            'paystack' => $this->paystack(),
            'wise' => $this->wise(),
            'stripe' => $this->stripe(),
            default => new UnconnectedPayoutGateway("No live payout rail for '{$rail}'."),
        };
    }

    /** The destination country for a vendor: explicit, else inferred from currency. */
    public function countryFor(Vendor $vendor): ?string
    {
        if (filled($vendor->country)) {
            return strtoupper($vendor->country);
        }

        if (filled($vendor->currency)) {
            return $this->catalog->countryForCurrency($vendor->currency);
        }

        return null;
    }

    /** Fallback rail when there's no vendor (balance/reconcile): the business's send bank, else the first usable rail. */
    private function defaultRail(User $user): ?string
    {
        if ($user->dwollaConnected() && $this->catalog->isUsable('dwolla')) {
            return 'dwolla';
        }

        foreach (array_keys($this->catalog->all()) as $rail) {
            if ($this->catalog->isUsable($rail)) {
                return $rail;
            }
        }

        return null;
    }

    private function dwolla(User $user): PayoutGateway
    {
        $cfg = config('handlemyap.dwolla');

        if (filled($cfg['key'] ?? null) && filled($cfg['secret'] ?? null)) {
            $source = $user->dwolla_funding_source_id ?: ($cfg['source_funding_source'] ?? null);

            if (filled($source)) {
                return new DwollaGateway($cfg['key'], $cfg['secret'], $cfg['env'] ?? 'sandbox', $source);
            }
        }

        return new UnconnectedPayoutGateway('Connect a bank for ACH payouts in Settings to pay US vendors.');
    }

    private function paystack(): PayoutGateway
    {
        $secret = config('handlemyap.paystack.secret');

        return filled($secret)
            ? new PaystackGateway($secret)
            : new UnconnectedPayoutGateway('Paystack is not configured for Nigerian payouts.');
    }

    private function wise(): PayoutGateway
    {
        $cfg = config('handlemyap.wise');

        return filled($cfg['token'] ?? null) && filled($cfg['profile_id'] ?? null)
            ? new WiseGateway($cfg['token'], (string) $cfg['profile_id'], $cfg['env'] ?? 'sandbox')
            : new UnconnectedPayoutGateway('Wise is not configured for UK payouts.');
    }

    private function stripe(): PayoutGateway
    {
        $cfg = config('handlemyap.stripe');

        return filled($cfg['secret'] ?? null)
            ? new StripeGlobalPayoutsGateway($cfg['secret'], $cfg['financial_account'] ?? null, $cfg['version'] ?? '2026-09-30.preview')
            : new UnconnectedPayoutGateway('Stripe Global Payouts is not configured for UK payouts.');
    }
}
