<?php

namespace App\Providers;

use App\Services\Extraction\AiInvoiceExtractor;
use App\Services\Extraction\InvoiceExtractor;
use App\Services\Extraction\StubExtractor;
use App\Services\Payouts\PayPalCheckout;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InvoiceExtractor::class, function ($app): InvoiceExtractor {
            $cfg = $app['config']['handlemyap'];
            $useAi = $cfg['extractor'] === 'ai'
                || ($cfg['extractor'] === 'auto' && filled($cfg['ai']['key']));

            return $useAi
                ? new AiInvoiceExtractor($cfg['ai']['key'], $cfg['ai']['model'])
                : new StubExtractor;
        });

        // PayPal Checkout (pay-in) — the "Pay with PayPal" button's create/capture.
        $this->app->bind(PayPalCheckout::class, fn ($app) => new PayPalCheckout(
            (string) ($app['config']['handlemyap.paypal.client_id'] ?? ''),
            (string) ($app['config']['handlemyap.paypal.secret'] ?? ''),
            (string) ($app['config']['handlemyap.paypal.env'] ?? 'sandbox'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
