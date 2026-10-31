<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vendor's payout destination on ONE rail. Destinations are per-rail because
 * each provider identifies a bank differently — a Dwolla funding-source id, a
 * Paystack recipient code — so a vendor can hold several (one per country/rail
 * they're paid on). `recipient_ref` is the provider's destination id the gateway
 * sends to; `details` keeps display info (bank name, masked account).
 */
class VendorPayoutMethod extends Model
{
    protected $fillable = [
        'vendor_id', 'rail', 'recipient_ref', 'details', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
