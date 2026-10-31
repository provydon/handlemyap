<?php

namespace App\Services\Payouts;

use App\Models\Payout;
use App\Models\Vendor;

/**
 * A payout rail (Dwolla ACH, or the offline Fake). The app is written against this
 * interface only — no concrete provider leaks into the orchestrator, so swapping
 * or adding rails is a matter of adding a class, not touching the core.
 */
interface PayoutGateway
{
    /** Send money to the vendor for this payout. */
    public function send(Payout $payout, Vendor $vendor): PayoutResult;

    /**
     * Current settlement status of a payout, as a normalised token
     * (SUCCESS | FAILED | PENDING), or null if it can't be read.
     */
    public function status(string $reference): ?string;

    /**
     * The account's available balance as ['state' => 'ok', 'currency' => 'USD',
     * 'available' => float], or null if it can't be read. Read-only.
     *
     * @return array{state: string, currency?: string, available?: float, demo?: bool}|null
     */
    public function balance(): ?array;
}
