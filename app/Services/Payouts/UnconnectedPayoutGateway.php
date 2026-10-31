<?php

namespace App\Services\Payouts;

use App\Models\Payout;
use App\Models\Vendor;

/**
 * Stand-in for a business that hasn't connected a bank (ACH funding source) yet.
 * There is nothing to pay from, so we fail loudly rather than silently dropping
 * money.
 */
class UnconnectedPayoutGateway implements PayoutGateway
{
    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        return PayoutResult::failed('Connect a bank for ACH payouts in Settings to pay.');
    }

    public function status(string $reference): ?string
    {
        return null;
    }

    public function balance(): ?array
    {
        return null;
    }
}
