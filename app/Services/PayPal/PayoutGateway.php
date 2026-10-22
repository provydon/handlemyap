<?php

namespace App\Services\PayPal;

use App\Models\Payout;
use App\Models\Vendor;

interface PayoutGateway
{
    /** Send money to the vendor for this payout. */
    public function send(Payout $payout, Vendor $vendor): PayoutResult;
}
