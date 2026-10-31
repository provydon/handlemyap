<?php

namespace App\Services\Payouts;

use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Support\Facades\Http;

/**
 * Paystack transfer rail — pays Nigerian vendors by bank transfer (NGN). Money
 * moves from the PLATFORM Paystack balance to the vendor's transfer recipient
 * (an RCP_… code created once from their bank code + NUBAN account number; see
 * PaystackConnector). test vs live is decided by the secret-key prefix, so there
 * is one base URL.
 *
 * Transfers are asynchronous: a created transfer is usually 'pending'/'success'
 * and settles shortly after — reconciled via status(). Amounts are in kobo.
 */
class PaystackGateway implements PayoutGateway
{
    private const BASE = 'https://api.paystack.co';

    public function __construct(private readonly string $secret) {}

    public function send(Payout $payout, Vendor $vendor): PayoutResult
    {
        $method = $vendor->payoutMethod('paystack');
        if (! $method || blank($method->recipient_ref)) {
            return PayoutResult::failed(
                'Vendor has no Nigerian bank connected for Paystack — add their bank details first.'
            );
        }

        $response = Http::withToken($this->secret)->post(self::BASE.'/transfer', [
            'source' => 'balance',
            'amount' => (int) round(((float) $payout->amount) * 100), // kobo
            'recipient' => $method->recipient_ref,
            'currency' => strtoupper($payout->currency ?: 'NGN'),
            'reference' => 'hma-'.$payout->id,
            'reason' => 'Invoice payout',
        ]);

        if ($response->failed()) {
            return PayoutResult::failed(
                'Paystack transfer rejected: '.$this->errorMessage($response),
                (string) $response->status(),
            );
        }

        $data = $response->json('data', []);
        $status = strtolower((string) ($data['status'] ?? 'pending'));
        $code = $data['transfer_code'] ?? null;

        return match ($status) {
            'success' => PayoutResult::accepted('paid', $code, null, $status),
            'failed', 'reversed', 'abandoned' => PayoutResult::failed('Paystack transfer failed', $status),
            default => PayoutResult::accepted('processing', $code, null, $status), // pending / otp / queued / received
        };
    }

    public function status(string $reference): ?string
    {
        $response = Http::withToken($this->secret)->get(self::BASE.'/transfer/'.$reference);

        if ($response->failed()) {
            return null;
        }

        return match (strtolower((string) $response->json('data.status'))) {
            'success' => 'SUCCESS',
            'failed', 'reversed', 'abandoned' => 'FAILED',
            default => 'PENDING',
        };
    }

    public function balance(): ?array
    {
        $response = Http::withToken($this->secret)->get(self::BASE.'/balance');

        if ($response->failed()) {
            return null;
        }

        $row = collect($response->json('data', []))->first();
        if (! $row) {
            return ['state' => 'pending'];
        }

        return [
            'state' => 'ok',
            'currency' => strtoupper((string) ($row['currency'] ?? 'NGN')),
            'available' => ((int) ($row['balance'] ?? 0)) / 100,
        ];
    }

    private function errorMessage($response): string
    {
        return $response->json('message', 'unknown error');
    }
}
