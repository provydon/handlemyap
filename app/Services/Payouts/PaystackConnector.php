<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Onboards a Nigerian vendor's bank into Paystack as a TRANSFER RECIPIENT (an
 * RCP_… code), using the PLATFORM secret. That code is the destination the
 * PaystackGateway transfers to — we store only the code, never raw details
 * beyond what's needed to display the account. Also exposes the Paystack bank
 * list (for the dropdown) and account-name resolution.
 */
class PaystackConnector
{
    private const BASE = 'https://api.paystack.co';

    public function __construct(private readonly string $secret) {}

    public function configured(): bool
    {
        return filled($this->secret);
    }

    /**
     * @return array{ok: bool, recipient_code: ?string, bank_name: ?string, account_name: ?string, message: ?string}
     */
    public function createTransferRecipient(string $name, string $accountNumber, string $bankCode, string $currency = 'NGN'): array
    {
        $response = Http::withToken($this->secret)->post(self::BASE.'/transferrecipient', [
            'type' => 'nuban',
            'name' => $name,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => strtoupper($currency),
        ]);

        if ($response->failed()) {
            return $this->fail($response->json('message', 'Could not create the Paystack recipient.'));
        }

        $data = $response->json('data', []);

        return [
            'ok' => true,
            'recipient_code' => $data['recipient_code'] ?? null,
            'bank_name' => $data['details']['bank_name'] ?? null,
            'account_name' => $data['details']['account_name'] ?? null,
            'message' => null,
        ];
    }

    /**
     * The Paystack bank list for a currency, as [['name' => ..., 'code' => ...]].
     * Cached for a day; returns [] if it can't be fetched (UI degrades to a
     * free-text bank-code input).
     *
     * @return array<int, array{name: string, code: string}>
     */
    public function banks(string $currency = 'NGN'): array
    {
        if (! $this->configured()) {
            return [];
        }

        return Cache::remember('paystack:banks:'.strtoupper($currency), now()->addDay(), function () use ($currency) {
            $response = Http::withToken($this->secret)->get(self::BASE.'/bank', ['currency' => strtoupper($currency)]);

            if ($response->failed()) {
                return [];
            }

            return collect($response->json('data', []))
                ->map(fn ($b) => ['name' => $b['name'] ?? '', 'code' => $b['code'] ?? ''])
                ->filter(fn ($b) => $b['code'] !== '')
                ->values()
                ->all();
        });
    }

    /** Resolve an account name from number + bank code, or null. */
    public function resolveAccount(string $accountNumber, string $bankCode): ?string
    {
        $response = Http::withToken($this->secret)->get(self::BASE.'/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]);

        return $response->successful() ? $response->json('data.account_name') : null;
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'recipient_code' => null, 'bank_name' => null, 'account_name' => null, 'message' => $message];
    }
}
