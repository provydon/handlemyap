<?php

namespace App\Services\PayPal;

use App\Models\Payout;
use App\Models\Vendor;

/**
 * Local/test gateway — pretends the payout settled instantly. Lets the entire
 * flow run (and be asserted) with no PayPal credentials or network.
 */
class FakePayoutGateway implements PayoutGateway
{
    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        return PayoutResult::accepted(
            status: 'paid',
            batchId: 'FAKE-BATCH-'.$payout->id,
            itemId: 'FAKE-ITEM-'.$payout->id,
            rawStatus: 'SUCCESS',
        );
    }

    public function status(string $batchId): ?string
    {
        return 'SUCCESS';
    }

    public function balance(): ?array
    {
        // No real account in offline/demo mode — a believable figure for the UI.
        return ['currency' => 'USD', 'available' => 2500.00, 'demo' => true];
    }
}
