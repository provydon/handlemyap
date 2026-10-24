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

    /**
     * The account's available balance as ['currency' => 'USD', 'available' => float],
     * or null if it can't be read (e.g. the Reporting / "Transaction Search"
     * product isn't enabled on the PayPal app). Read-only.
     *
     * @return array{currency: string, available: float, demo?: bool}|null
     */
    public function balance(): ?array;
}
