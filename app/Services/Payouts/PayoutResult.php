<?php

namespace App\Services\Payouts;

/**
 * Outcome of asking the gateway to send money. Rails differ: some settle
 * synchronously (an ACH transfer), some accept-then-settle asynchronously,
 * so "ok" can mean paid OR accepted-and-processing — settlement is confirmed
 * later via status sync / webhook.
 */
final readonly class PayoutResult
{
    public function __construct(
        public bool $ok,
        public string $status,           // paid | processing | failed
        public ?string $reference = null,     // provider transfer/batch id
        public ?string $itemReference = null, // provider line-item id, where applicable
        public ?string $rawStatus = null,
        public ?string $error = null,
    ) {}

    public static function accepted(string $status, ?string $reference, ?string $itemReference = null, ?string $rawStatus = null): self
    {
        return new self(ok: true, status: $status, reference: $reference, itemReference: $itemReference, rawStatus: $rawStatus);
    }

    public static function failed(string $error, ?string $rawStatus = null): self
    {
        return new self(ok: false, status: 'failed', rawStatus: $rawStatus, error: $error);
    }
}
