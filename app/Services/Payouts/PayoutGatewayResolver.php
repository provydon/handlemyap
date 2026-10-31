<?php

namespace App\Services\Payouts;

use App\Models\User;

/**
 * Resolves the payout gateway for a specific user. Credentials are PLATFORM-level
 * (one Dwolla app for everyone); what's per-business is the *source bank* money
 * is paid from — the user's own connected funding source, falling back to a
 * platform default:
 *
 *   - gateway=fake (tests / offline demo)        → the bound FakePayoutGateway
 *   - platform creds + a source funding source   → a DwollaGateway
 *   - otherwise                                   → UnconnectedPayoutGateway
 */
class PayoutGatewayResolver
{
    public function for(User $user): PayoutGateway
    {
        if (config('handlemyap.gateway') === 'fake') {
            return app(PayoutGateway::class);
        }

        $cfg = config('handlemyap.dwolla');

        if (filled($cfg['key'] ?? null) && filled($cfg['secret'] ?? null)) {
            $source = $user->dwolla_funding_source_id ?: ($cfg['source_funding_source'] ?? null);

            if (filled($source)) {
                return new DwollaGateway($cfg['key'], $cfg['secret'], $cfg['env'] ?? 'sandbox', $source);
            }
        }

        return new UnconnectedPayoutGateway;
    }
}
