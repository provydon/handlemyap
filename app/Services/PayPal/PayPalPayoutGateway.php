<?php

namespace App\Services\PayPal;

use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Support\Facades\Http;

/**
 * Real PayPal Payouts (sandbox by default). Sends money to a vendor by email —
 * the recipient doesn't need to pre-accept PayPal; they claim the payout. This
 * is why "the vendor doesn't use PayPal" isn't a blocker here.
 *
 * Flow: OAuth client-credentials token → POST /v1/payments/payouts. The batch
 * comes back PENDING/PROCESSING and settles asynchronously; we persist the
 * batch id and reconcile status later.
 */
class PayPalPayoutGateway implements PayoutGateway
{
    public function __construct(
        private readonly string $base,
        private readonly string $clientId,
        private readonly string $secret,
    ) {}

    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        if (! $vendor->email) {
            return PayoutResult::failed('Vendor has no payout email');
        }

        $token = $this->accessToken();
        if ($token === null) {
            return PayoutResult::failed('Could not authenticate with PayPal');
        }

        $batchId = 'HMA-'.$payout->id.'-'.now()->timestamp;

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->base.'/v1/payments/payouts', [
                'sender_batch_header' => [
                    'sender_batch_id' => $batchId,
                    'email_subject' => 'You have a payment',
                    'email_message' => 'A payment has been sent to you via handlemyap.',
                ],
                'items' => [[
                    'recipient_type' => 'EMAIL',
                    'receiver' => $vendor->email,
                    'amount' => [
                        'value' => number_format((float) $payout->amount, 2, '.', ''),
                        'currency' => $payout->currency,
                    ],
                    'note' => 'Invoice payment',
                    'sender_item_id' => (string) $payout->id,
                ]],
            ]);

        if ($response->failed()) {
            return PayoutResult::failed(
                'PayPal payout rejected: '.$response->json('message', 'unknown error'),
                $response->json('name'),
            );
        }

        $batch = $response->json('batch_header.payout_batch_id');
        $status = strtoupper((string) $response->json('batch_header.batch_status', 'PENDING'));

        return PayoutResult::accepted(
            status: $this->mapStatus($status),
            batchId: $batch,
            rawStatus: $status,
        );
    }

    public function status(string $batchId): ?string
    {
        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)->acceptJson()
            ->get($this->base.'/v1/payments/payouts/'.$batchId);

        if ($response->failed()) {
            return null;
        }

        return strtoupper((string) $response->json('batch_header.batch_status'));
    }

    public function balance(): ?array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)->acceptJson()
            ->get($this->base.'/v1/reporting/balances');

        if ($response->failed()) {
            return null; // e.g. the Transaction Search / Reporting product isn't enabled (403)
        }

        $balances = $response->json('balances', []);

        // 200 with no balances = a real (reachable) account that simply holds no
        // funds yet — show zero rather than hiding the metric.
        if (empty($balances)) {
            return ['currency' => 'USD', 'available' => 0.0];
        }

        // Prefer the primary balance; fall back to the first currency.
        $primary = collect($balances)->firstWhere('primary', true) ?? $balances[0];

        return [
            'currency' => $primary['available_balance']['currency_code'] ?? ($primary['currency'] ?? 'USD'),
            'available' => (float) ($primary['available_balance']['value'] ?? 0),
        ];
    }

    private function accessToken(): ?string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->secret)
            ->post($this->base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->successful() ? $response->json('access_token') : null;
    }

    private function mapStatus(string $paypalStatus): string
    {
        return match ($paypalStatus) {
            'SUCCESS' => 'paid',
            'DENIED', 'CANCELED' => 'failed',
            default => 'processing', // PENDING / PROCESSING / NEW
        };
    }
}
