<?php

namespace App\Services\Payouts;

use Illuminate\Support\Facades\Http;

/**
 * Onboards a bank account into Dwolla as a funding source, using the PLATFORM
 * credentials. Creates a receive-only Customer and attaches a funding source
 * from raw routing/account numbers — the minimum to get a funding-source id the
 * gateway can transfer to.
 *
 * ONBOARDING SEAM: a funding source added this way is UNVERIFIED. Receiving ACH
 * is fine unverified; *sending* (a paying business's source) requires a verified
 * customer via micro-deposits / Plaid / instant account verification. Wiring
 * that verification is the onboarding seam and is intentionally out of scope —
 * this creates the record so the rest of the flow (and the demo) can run.
 */
class DwollaConnector
{
    private const HAL = 'application/vnd.dwolla.v1.hal+json';

    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $env,
    ) {}

    /**
     * @param  array{name: string, email?: ?string, routing_number: string, account_number: string, account_type?: ?string, business_name?: ?string}  $bank
     * @return array{ok: bool, customer_id: ?string, funding_source_id: ?string, message: ?string}
     */
    public function createFundingSource(array $bank): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return $this->fail('Could not authenticate with Dwolla.');
        }

        $http = fn () => Http::withToken($token)->withHeaders(['Accept' => self::HAL]);

        // 1) A receive-only customer to own the funding source.
        [$first, $last] = $this->splitName($bank['name']);
        $customer = $http()->post($this->base().'/customers', array_filter([
            'firstName' => $first,
            'lastName' => $last,
            'email' => $bank['email'] ?? null,
            'businessName' => $bank['business_name'] ?? null,
            'type' => 'receive-only',
        ], fn ($v) => $v !== null));

        if ($customer->failed()) {
            return $this->fail('Dwolla customer rejected: '.$this->errorMessage($customer));
        }
        $customerUrl = $customer->header('Location');
        $customerId = $this->idFromLocation($customerUrl);

        // 2) Attach the bank account as a funding source on that customer.
        $funding = $http()->post(rtrim($customerUrl, '/').'/funding-sources', [
            'routingNumber' => $bank['routing_number'],
            'accountNumber' => $bank['account_number'],
            'bankAccountType' => $bank['account_type'] ?? 'checking',
            'name' => $bank['name'],
        ]);

        if ($funding->failed()) {
            return $this->fail('Dwolla funding source rejected: '.$this->errorMessage($funding));
        }

        return [
            'ok' => true,
            'customer_id' => $customerId,
            'funding_source_id' => $this->idFromLocation($funding->header('Location')),
            'message' => null,
        ];
    }

    /**
     * Instant-verification path: create a sendable customer and attach a
     * Plaid-verified funding source (via its Dwolla processor token). The
     * resulting funding source is VERIFIED, so it can send ACH immediately —
     * no micro-deposit wait.
     *
     * NOTE: the customer is created unverified (can send within Dwolla's
     * unverified limits). Upgrading it to a fully "Verified Customer" for higher
     * limits / business sending needs KYC fields (address, DOB, SSN/EIN) and is
     * the remaining seam — the Plaid-verified *funding source* is what unblocks
     * sending here.
     *
     * @param  array{name: string, email?: ?string, business_name?: ?string}  $business
     * @return array{ok: bool, customer_id: ?string, funding_source_id: ?string, message: ?string}
     */
    public function createVerifiedFundingSourceFromPlaid(array $business, string $processorToken): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return $this->fail('Could not authenticate with Dwolla.');
        }

        $http = fn () => Http::withToken($token)->withHeaders(['Accept' => self::HAL]);

        [$first, $last] = $this->splitName($business['name']);
        $customer = $http()->post($this->base().'/customers', array_filter([
            'firstName' => $first,
            'lastName' => $last,
            'email' => $business['email'] ?? null,
            'businessName' => $business['business_name'] ?? null,
        ], fn ($v) => $v !== null));

        if ($customer->failed()) {
            return $this->fail('Dwolla customer rejected: '.$this->errorMessage($customer));
        }
        $customerUrl = $customer->header('Location');

        // Attach the Plaid-verified bank as a funding source via its processor token.
        $funding = $http()->post(rtrim($customerUrl, '/').'/funding-sources', [
            'plaidToken' => $processorToken,
            'name' => $business['name'],
        ]);

        if ($funding->failed()) {
            return $this->fail('Dwolla funding source rejected: '.$this->errorMessage($funding));
        }

        return [
            'ok' => true,
            'customer_id' => $this->idFromLocation($customerUrl),
            'funding_source_id' => $this->idFromLocation($funding->header('Location')),
            'message' => null,
        ];
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'customer_id' => null, 'funding_source_id' => null, 'message' => $message];
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? 'Account', $parts[1] ?? 'Holder'];
    }

    private function base(): string
    {
        return in_array($this->env, ['production', 'prod', 'live'], true)
            ? 'https://api.dwolla.com'
            : 'https://api-sandbox.dwolla.com';
    }

    private function idFromLocation(?string $location): ?string
    {
        if (! $location) {
            return null;
        }

        return basename(parse_url($location, PHP_URL_PATH) ?: $location);
    }

    private function errorMessage($response): string
    {
        return $response->json('_embedded.errors.0.message')
            ?? $response->json('message', 'unknown error');
    }

    private function accessToken(): ?string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->key, $this->secret)
            ->post($this->base().'/token', ['grant_type' => 'client_credentials']);

        return $response->successful() ? (string) $response->json('access_token') : null;
    }
}
