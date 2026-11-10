<?php

namespace App\Services\Payouts;

use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Checkout (Orders v2) — the *pay-in* side of PayPal, as opposed to the
 * Payouts rail (PayPalGateway). This powers the "Pay with PayPal" button: the AP
 * user is sent to PayPal, logs in, and pays an invoice from their own balance or
 * card. Where the vendor has a PayPal email on file we set them as the `payee`,
 * so the money lands with the vendor rather than the platform.
 *
 * Two steps, bridged by a browser redirect:
 *   1. createOrder()  → an order + an approve URL we send the user to
 *   2. captureOrder() → once they approve and come back, we capture the money
 *
 * Platform REST app creds (client id + secret); sandbox vs live is a host switch.
 */
class PayPalCheckout
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

    /**
     * Create an order for an invoice and return the URL to send the payer to.
     *
     * @return array{ok: bool, id: ?string, approve_url: ?string, message: ?string}
     */
    public function createOrder(Invoice $invoice, string $returnUrl, string $cancelUrl, ?string $payeeEmail = null): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return $this->fail('Could not authenticate with PayPal.');
        }

        $purchaseUnit = [
            'amount' => [
                'currency_code' => strtoupper($invoice->currency ?: 'USD'),
                'value' => number_format((float) $invoice->amount, 2, '.', ''),
            ],
            'description' => $this->description($invoice),
            'custom_id' => 'invoice-'.$invoice->id,
        ];

        // Route the money to the vendor when we know their PayPal; otherwise it
        // settles to the platform account (fine for a sandbox demo).
        if (filled($payeeEmail)) {
            $purchaseUnit['payee'] = ['email_address' => $payeeEmail];
        }

        $response = Http::withToken($token)->post($this->base().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [$purchaseUnit],
            'application_context' => [
                'brand_name' => 'HandlemyAP',
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        if ($response->failed()) {
            return $this->fail('PayPal could not start the payment: '.$this->errorMessage($response));
        }

        $links = collect($response->json('links', []));
        $approve = $links->firstWhere('rel', 'payer-action')
            ?? $links->firstWhere('rel', 'approve');

        return [
            'ok' => true,
            'id' => $response->json('id'),
            'approve_url' => $approve['href'] ?? null,
            'message' => null,
        ];
    }

    /**
     * Capture an approved order. Returns the capture outcome.
     *
     * @return array{ok: bool, status: ?string, capture_id: ?string, message: ?string}
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            return ['ok' => false, 'status' => null, 'capture_id' => null, 'message' => 'Could not authenticate with PayPal.'];
        }

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->base().'/v2/checkout/orders/'.$orderId.'/capture');

        if ($response->failed()) {
            return ['ok' => false, 'status' => null, 'capture_id' => null, 'message' => 'PayPal could not capture the payment: '.$this->errorMessage($response)];
        }

        $status = strtoupper((string) $response->json('status'));
        $captureId = $response->json('purchase_units.0.payments.captures.0.id');

        return [
            'ok' => $status === 'COMPLETED',
            'status' => $status,
            'capture_id' => $captureId,
            'message' => $status === 'COMPLETED' ? null : 'PayPal payment not completed (status: '.$status.').',
        ];
    }

    private function description(Invoice $invoice): string
    {
        $vendor = $invoice->vendor?->name ?: $invoice->vendor_name ?: 'vendor';
        $number = $invoice->invoice_number ? ' #'.$invoice->invoice_number : '';

        return mb_substr("Invoice{$number} — {$vendor}", 0, 127);
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'id' => null, 'approve_url' => null, 'message' => $message];
    }

    private function base(): string
    {
        return in_array($this->env, ['production', 'prod', 'live'], true)
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function errorMessage($response): string
    {
        return $response->json('details.0.description')
            ?? $response->json('message', 'unknown error');
    }

    private function accessToken(): ?string
    {
        $key = 'paypal:token:'.md5($this->base().'|'.$this->clientId);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->secret)
            ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            return null;
        }

        $token = (string) $response->json('access_token');
        $ttl = (int) ($response->json('expires_in') ?? 32400);
        Cache::put($key, $token, max(60, $ttl - 60));

        return $token;
    }
}
