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
}
