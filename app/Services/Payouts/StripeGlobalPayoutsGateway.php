<?php

namespace App\Services\Payouts;

use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Global Payouts rail — pays UK vendors via the OutboundPayments v2 API,
 * from the platform's financial account. The destination is a Stripe v2 recipient
 * (a v2 Account with the recipient configuration) plus one of its payout methods;
 * both ids are created once from the vendor's bank details (see
 * StripeGlobalPayoutsConnector) and stored on the vendor's payout method.
 *
 * NOTE: the v2 money-management APIs are preview-versioned (pinned via the
 * Stripe-Version header). The endpoints and the object shape below follow
 * Stripe's Global Payouts docs; confirm field names against the live API /
 * sandbox before enabling production. Amounts are minor units; currency lower-case.
 */
class StripeGlobalPayoutsGateway implements PayoutGateway
{
    private const BASE = 'https://api.stripe.com';

    public function __construct(
        private readonly string $secret,
        private readonly ?string $financialAccount,
        private readonly string $version,
    ) {}

    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        $method = $vendor->payoutMethod('stripe');
        $recipient = $method?->recipient_ref;
        $payoutMethod = $method?->details['payout_method'] ?? null;

        if (blank($recipient) || blank($payoutMethod)) {
            return PayoutResult::failed('Vendor has no UK bank connected for Stripe — add their bank details first.');
        }
        if (blank($this->financialAccount)) {
            return PayoutResult::failed('No Stripe financial account is configured to pay from.');
        }

        $response = $this->http()->post(self::BASE.'/v2/money_management/outbound_payments', [
            'amount' => [
                'value' => (int) round(((float) $payout->amount) * 100),
                'currency' => strtolower($payout->currency ?: 'gbp'),
            ],
            'from' => ['financial_account' => $this->financialAccount],
            'to' => ['recipient' => $recipient, 'payout_method' => $payoutMethod],
            'description' => 'Invoice payout '.$payout->id,
        ]);

        if ($response->failed()) {
            return PayoutResult::failed('Stripe payout rejected: '.$this->errorMessage($response), (string) $response->status());
        }

        $id = (string) $response->json('id');
        $status = strtolower((string) $response->json('status'));

        return match ($status) {
            'posted', 'paid' => PayoutResult::accepted('paid', $id, null, $status),
            'failed', 'returned', 'canceled', 'cancelled' => PayoutResult::failed('Stripe payout failed', $status),
            default => PayoutResult::accepted('processing', $id, null, $status),
        };
    }

    public function status(string $reference): ?string
    {
        $response = $this->http()->get(self::BASE.'/v2/money_management/outbound_payments/'.$reference);

        if ($response->failed()) {
            return null;
        }

        return match (strtolower((string) $response->json('status'))) {
            'posted', 'paid' => 'SUCCESS',
            'failed', 'returned', 'canceled', 'cancelled' => 'FAILED',
            default => 'PENDING',
        };
    }

    public function balance(): ?array
    {
        if (blank($this->financialAccount)) {
            return null;
        }

        $response = $this->http()->get(self::BASE.'/v2/money_management/financial_accounts/'.$this->financialAccount);

        if ($response->failed()) {
            return null;
        }

        // balance.available is a list of monetary amounts (one per currency).
        $amount = collect($response->json('balance.available', []))->first();
        if (! $amount) {
            return ['state' => 'pending'];
        }

        return [
            'state' => 'ok',
            'currency' => strtoupper((string) ($amount['currency'] ?? 'GBP')),
            'available' => ((int) ($amount['value'] ?? 0)) / 100,
        ];
    }

    private function http()
    {
        return Http::withToken($this->secret)
            ->withHeaders(['Stripe-Version' => $this->version])
            ->acceptJson();
    }

    private function errorMessage($response): string
    {
        return $response->json('error.message')
            ?? $response->json('message', 'unknown error');
    }
}
