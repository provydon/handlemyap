<?php

namespace App\Services\PayPal;

use App\Models\Payout;
use App\Models\Vendor;

interface PayoutGateway
{
    /** Send money to the vendor for this payout. */
    public function send(Payout $payout, Vendor $vendor): PayoutResult;

    /**
     * Current settlement status of a payout batch, as the provider's raw string
     * (e.g. PENDING, PROCESSING, SUCCESS, DENIED), or null if it can't be read.
     */
    public function status(string $batchId): ?string;
}
