<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Http;

/**
 * Onboards a UK vendor into Stripe Global Payouts: a v2 recipient Account plus a
 * bank-account PAYOUT METHOD. The gateway needs both ids (recipient + payout
 * method) to send, so createRecipient returns both; we store only the ids.
 *
 * NOTE: these are preview-versioned v2 endpoints — confirm the exact request
 * shape against Stripe's Global Payouts docs / sandbox before enabling live.
 */
class StripeGlobalPayoutsConnector
{
    private const BASE = 'https://api.stripe.com';

    public function __construct(
        private readonly string $secret,
        private readonly string $version,
    ) {}

    public function configured(): bool
    {
        return filled($this->secret);
    }

    /**
     * @return array{ok: bool, recipient_code: ?string, payout_method: ?string, message: ?string}
     */
    public function createRecipient(string $name, string $sortCode, string $accountNumber, string $currency = 'GBP'): array
    {
        // 1) A recipient account (v2 Account with the recipient configuration).
        $account = $this->http()->post(self::BASE.'/v2/core/accounts', [
            'display_name' => $name,
            'configuration' => ['recipient' => new \stdClass],
        ]);
        if ($account->failed()) {
            return $this->fail($this->errorMessage($account, 'Could not create the Stripe recipient.'));
        }
        $recipientId = (string) $account->json('id');

        // 2) Its bank-account payout method (the destination credentials).
        $method = $this->http()->post(self::BASE."/v2/core/accounts/{$recipientId}/payout_methods", [
            'type' => 'bank_account',
            'bank_account' => [
                'country' => 'GB',
                'currency' => strtolower($currency),
                'account_holder_name' => $name,
                'sort_code' => $sortCode,
                'account_number' => $accountNumber,
            ],
        ]);
        if ($method->failed()) {
            return $this->fail($this->errorMessage($method, 'Could not add this bank with Stripe.'));
        }

        return [
            'ok' => true,
            'recipient_code' => $recipientId,
            'payout_method' => (string) $method->json('id'),
            'message' => null,
        ];
    }

    private function http()
    {
        return Http::withToken($this->secret)
            ->withHeaders(['Stripe-Version' => $this->version])
            ->acceptJson();
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'recipient_code' => null, 'payout_method' => null, 'message' => $message];
    }

    private function errorMessage($response, string $default): string
    {
        return $response->json('error.message') ?? $response->json('message', $default);
    }
}
