<?php

namespace App\Services\Payouts;

use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Plaid — instant bank verification. The business links their bank in Plaid Link
 * (frontend), and we turn the resulting public_token into a Dwolla *processor
 * token* that creates a VERIFIED Dwolla funding source — one that can actually
 * send ACH, with no micro-deposit wait.
 *
 * Plaid auth is client_id + secret in the JSON body (not headers).
 */
class PlaidConnector
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $secret,
        private readonly string $env,
    ) {}

    public function configured(): bool
    {
        return filled($this->clientId) && filled($this->secret);
    }

    /** A short-lived token the frontend hands to Plaid Link to open the flow. */
    public function createLinkToken(User $user): ?string
    {
        $response = $this->post('/link/token/create', [
            'user' => ['client_user_id' => (string) $user->id],
            'client_name' => 'HandlemyAP',
            'products' => ['auth'],
            'country_codes' => ['US'],
            'language' => 'en',
        ]);

        return $response?->json('link_token');
    }

    /** Exchange the public_token Link returns for a durable access_token. */
    public function exchangePublicToken(string $publicToken): ?string
    {
        $response = $this->post('/item/public_token/exchange', [
            'public_token' => $publicToken,
        ]);

        return $response?->json('access_token');
    }

    /** Mint a Dwolla processor token for the chosen account. */
    public function createDwollaProcessorToken(string $accessToken, string $accountId): ?string
    {
        $response = $this->post('/processor/token/create', [
            'access_token' => $accessToken,
            'account_id' => $accountId,
            'processor' => 'dwolla',
        ]);

        return $response?->json('processor_token');
    }

    private function post(string $path, array $body)
    {
        $response = Http::acceptJson()->post($this->base().$path, array_merge([
            'client_id' => $this->clientId,
            'secret' => $this->secret,
        ], $body));

        return $response->successful() ? $response : null;
    }

    private function base(): string
    {
        return match ($this->env) {
            'production' => 'https://production.plaid.com',
            'development' => 'https://development.plaid.com',
            default => 'https://sandbox.plaid.com',
        };
    }
}
