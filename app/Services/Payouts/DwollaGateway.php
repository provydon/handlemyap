<?php

namespace App\Services\Payouts;

use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Dwolla ACH payout rail. Unlike card rails, ACH is exactly what most vendors
 * want: give bank details, get paid. The model is PLATFORM-level — one Dwolla
 * app credential (key/secret) authenticates every call; money moves between
 * *funding sources*:
 *
 *   source (the paying business's bank)  →  destination (the vendor's bank)
 *
 * Both are funding-source hrefs under the platform's Dwolla account. ACH is
 * asynchronous, so a created transfer starts PROCESSING and settles (processed)
 * or fails days later — reconciled via status().
 *
 * ONBOARDING SEAM: a real source funding source must be a *verified* customer
 * (micro-deposits / Plaid / IAV) before it can send; vendor destinations can be
 * receive-only. That verification is created via DwollaConnector and is the
 * onboarding seam — out of scope here. This class just moves money once the
 * funding-source ids exist.
 */
class DwollaGateway implements PayoutGateway
{
    private const HAL = 'application/vnd.dwolla.v1.hal+json';

    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $env,
        private readonly ?string $sourceFundingSource,
    ) {}

    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        $destination = $vendor->payoutMethod('dwolla')?->recipient_ref;

        if (blank($destination)) {
            return PayoutResult::failed(
                'Vendor has no bank connected for ACH — add their bank details first.'
            );
        }
        if (blank($this->sourceFundingSource)) {
            return PayoutResult::failed('No source bank is configured for ACH payouts.');
        }

        $token = $this->accessToken();
        if ($token === null) {
            return PayoutResult::failed('Could not authenticate with Dwolla');
        }

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => self::HAL])
            ->post($this->base().'/transfers', [
                '_links' => [
                    'source' => ['href' => $this->fundingSourceHref($this->sourceFundingSource)],
                    'destination' => ['href' => $this->fundingSourceHref($destination)],
                ],
                'amount' => [
                    'currency' => strtoupper($payout->currency ?: 'USD'),
                    'value' => number_format((float) $payout->amount, 2, '.', ''),
                ],
            ]);

        if ($response->failed()) {
            return PayoutResult::failed(
                'Dwolla transfer rejected: '.$this->errorMessage($response),
                (string) $response->status(),
            );
        }

        // Dwolla returns the new transfer URL in the Location header.
        $id = $this->idFromLocation($response->header('Location'));

        return PayoutResult::accepted(
            status: 'processing',          // ACH clears over days; reconcile later
            reference: $id,
            rawStatus: 'pending',
        );
    }

    public function status(string $reference): ?string
    {
        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)->withHeaders(['Accept' => self::HAL])
            ->get($this->base().'/transfers/'.$reference);

        if ($response->failed()) {
            return null;
        }

        // Normalise Dwolla's transfer status to our tokens.
        return match (strtolower((string) $response->json('status'))) {
            'processed' => 'SUCCESS',
            'failed', 'cancelled', 'canceled' => 'FAILED',
            default => 'PENDING', // pending / processing
        };
    }

    public function balance(): ?array
    {
        if (blank($this->sourceFundingSource)) {
            return null;
        }

        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)->withHeaders(['Accept' => self::HAL])
            ->get($this->base().'/funding-sources/'.$this->sourceFundingSource.'/balance');

        if ($response->failed()) {
            return null;
        }

        $value = $response->json('balance.value');
        if ($value === null) {
            return ['state' => 'pending'];
        }

        return [
            'state' => 'ok',
            'currency' => strtoupper((string) $response->json('balance.currency', 'USD')),
            'available' => (float) $value,
        ];
    }

    private function base(): string
    {
        return in_array($this->env, ['production', 'prod', 'live'], true)
            ? 'https://api.dwolla.com'
            : 'https://api-sandbox.dwolla.com';
    }

    /** Accept either a bare funding-source id or an already-qualified href. */
    private function fundingSourceHref(string $idOrHref): string
    {
        return str_starts_with($idOrHref, 'http')
            ? $idOrHref
            : $this->base().'/funding-sources/'.$idOrHref;
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
        // App tokens are valid for an hour — cache and reuse so we don't do a
        // token round-trip before every transfer/status/balance call.
        $key = 'dwolla:token:'.md5($this->base().'|'.$this->key);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->key, $this->secret)
            ->post($this->base().'/token', ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            return null;
        }

        $token = (string) $response->json('access_token');
        $ttl = (int) ($response->json('expires_in') ?? 3600);
        Cache::put($key, $token, max(60, $ttl - 60)); // refresh a minute early

        return $token;
    }
}
