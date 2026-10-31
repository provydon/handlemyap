<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Http;

/**
 * Onboards a UK vendor's bank into Wise as a RECIPIENT ACCOUNT (a numeric id),
 * using the platform token + profile. That id is the destination the WiseGateway
 * transfers to — we store only the id (plus display info), never raw details.
 */
class WiseConnector
{
    public function __construct(
        private readonly string $token,
        private readonly string $profileId,
        private readonly string $env,
    ) {}

    public function configured(): bool
    {
        return filled($this->token) && filled($this->profileId);
    }

    /**
     * @return array{ok: bool, recipient_code: ?string, message: ?string}
     */
    public function createRecipient(string $name, string $sortCode, string $accountNumber, string $currency = 'GBP'): array
    {
        $response = Http::withToken($this->token)->acceptJson()->post($this->base().'/v1/accounts', [
            'currency' => strtoupper($currency),
            'type' => 'sort_code',
            'profile' => (int) $this->profileId,
            'accountHolderName' => $name,
            'legalType' => 'PRIVATE',
            'details' => [
                'legalType' => 'PRIVATE',
                'sortCode' => $sortCode,
                'accountNumber' => $accountNumber,
            ],
        ]);

        if ($response->failed()) {
            return ['ok' => false, 'recipient_code' => null, 'message' => $response->json('errors.0.message') ?? $response->json('message', 'Could not add this bank with Wise.')];
        }

        return ['ok' => true, 'recipient_code' => (string) $response->json('id'), 'message' => null];
    }

    private function base(): string
    {
        return in_array($this->env, ['live', 'production', 'prod'], true)
            ? 'https://api.wise.com'
            : 'https://api.sandbox.transferwise.tech';
    }
}
