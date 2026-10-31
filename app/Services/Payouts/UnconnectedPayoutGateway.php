<?php

namespace App\Services\Payouts;

use App\Models\Payout;
use App\Models\Vendor;

/**
 * Stand-in for when there's no usable rail for a payout — the vendor's country
 * has no connected rail, or the business hasn't connected a bank to send from.
 * It fails loudly (with the specific reason) rather than silently dropping money.
 */
class UnconnectedPayoutGateway implements PayoutGateway
{
    public function __construct(
        private readonly string $reason = 'Connect a bank for payouts in Settings to pay.',
    ) {}

    public function reason(): string
    {
        return $this->reason;
    }

    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        return PayoutResult::failed($this->reason);
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
