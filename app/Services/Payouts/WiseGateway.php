<?php

namespace App\Services\Payouts;

use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Wise payout rail — pays UK vendors by bank transfer (GBP), funded from the
 * platform's Wise balance. A Wise payout is four calls (all against one profile):
 *
 *   quote  →  transfer (to the vendor's recipient account)  →  fund from BALANCE
 *
 * The recipient account (an id created once from sort code + account number; see
 * WiseConnector) is the destination. Transfers settle asynchronously, so a funded
 * transfer starts PROCESSING and reaches outgoing_payment_sent later — reconciled
 * via status(). Amounts are major units (GBP, not pence).
 */
class WiseGateway implements PayoutGateway
{
    public function __construct(
        private readonly string $token,
        private readonly string $profileId,
        private readonly string $env,
    ) {}

    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        $recipient = $vendor->payoutMethod('wise')?->recipient_ref;
        if (blank($recipient)) {
            return PayoutResult::failed('Vendor has no UK bank connected for Wise — add their bank details first.');
        }

        $currency = strtoupper($payout->currency ?: 'GBP');

        // 1) Quote (same-currency GBP→GBP; funded from balance).
        $quote = $this->http()->post($this->base()."/v3/profiles/{$this->profileId}/quotes", [
            'sourceCurrency' => $currency,
            'targetCurrency' => $currency,
            'targetAmount' => (float) $payout->amount,
            'payOut' => 'BANK_TRANSFER',
        ]);
        if ($quote->failed()) {
            return PayoutResult::failed('Wise quote rejected: '.$this->errorMessage($quote), (string) $quote->status());
        }

        // 2) Transfer to the recipient account.
        $transfer = $this->http()->post($this->base().'/v1/transfers', [
            'targetAccount' => $recipient,
            'quoteUuid' => $quote->json('id'),
            'customerTransactionId' => (string) Str::uuid(),
            'details' => ['reference' => 'HMA '.$payout->id],
        ]);
        if ($transfer->failed()) {
            return PayoutResult::failed('Wise transfer rejected: '.$this->errorMessage($transfer), (string) $transfer->status());
        }
        $transferId = (string) $transfer->json('id');

        // 3) Fund it from the Wise balance — money only moves once this succeeds.
        $fund = $this->http()->post(
            $this->base()."/v3/profiles/{$this->profileId}/transfers/{$transferId}/payments",
            ['type' => 'BALANCE'],
        );
        if ($fund->failed()) {
            return PayoutResult::failed('Wise funding failed: '.$this->errorMessage($fund), (string) $fund->status());
        }

        return PayoutResult::accepted(
            status: 'processing',      // settles over the rail; reconciled via status()
            reference: $transferId,
            rawStatus: (string) ($fund->json('status') ?? $transfer->json('status')),
        );
    }

    public function status(string $reference): ?string
    {
        $response = $this->http()->get($this->base().'/v1/transfers/'.$reference);

        if ($response->failed()) {
            return null;
        }

        return match (strtolower((string) $response->json('status'))) {
            'outgoing_payment_sent' => 'SUCCESS',
            'bounced_back', 'cancelled', 'funds_refunded', 'charged_back' => 'FAILED',
            default => 'PENDING',
        };
    }

    public function balance(): ?array
    {
        $response = $this->http()->get($this->base()."/v4/profiles/{$this->profileId}/balances", ['types' => 'STANDARD']);

        if ($response->failed()) {
            return null;
        }

        $balances = collect($response->json() ?? []);
        $row = $balances->firstWhere('amount.currency', 'GBP') ?? $balances->first();
        if (! $row) {
            return ['state' => 'pending'];
        }

        return [
            'state' => 'ok',
            'currency' => strtoupper((string) data_get($row, 'amount.currency', 'GBP')),
            'available' => (float) data_get($row, 'amount.value', 0),
        ];
    }

    private function http()
    {
        return Http::withToken($this->token)->acceptJson();
    }

    private function base(): string
    {
        return in_array($this->env, ['live', 'production', 'prod'], true)
            ? 'https://api.wise.com'
            : 'https://api.sandbox.transferwise.tech';
    }

    private function errorMessage($response): string
    {
        return $response->json('errors.0.message')
            ?? $response->json('message', 'unknown error');
    }
}
