<?php

namespace App\Services\PayPal;

/**
 * Outcome of asking the gateway to send money. PayPal Payouts are asynchronous,
 * so "ok" can mean accepted-and-processing; settlement is confirmed later via
 * status sync / webhook.
 */
final readonly class PayoutResult
{
    public function __construct(
        public bool $ok,
        public string $status,           // paid | processing | failed
        public ?string $batchId = null,
        public ?string $itemId = null,
        public ?string $rawStatus = null,
        public ?string $error = null,
    ) {}

    public static function accepted(string $status, ?string $batchId, ?string $itemId = null, ?string $rawStatus = null): self
    {
        return new self(ok: true, status: $status, batchId: $batchId, itemId: $itemId, rawStatus: $rawStatus);
    }

    public static function failed(string $error, ?string $rawStatus = null): self
    {
        return new self(ok: false, status: 'failed', rawStatus: $rawStatus, error: $error);
    }
}
