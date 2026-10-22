<?php

namespace App\Providers;

use App\Services\Extraction\AiInvoiceExtractor;
use App\Services\Extraction\InvoiceExtractor;
use App\Services\Extraction\StubExtractor;
use App\Services\PayPal\FakePayoutGateway;
use App\Services\PayPal\PayoutGateway;
use App\Services\PayPal\PayPalPayoutGateway;
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

        $this->app->bind(PayoutGateway::class, function ($app): PayoutGateway {
            $cfg = $app['config']['handlemyap'];
            $usePayPal = $cfg['gateway'] === 'paypal'
                || ($cfg['gateway'] === 'auto' && filled($cfg['paypal']['client_id']));

            return $usePayPal
                ? new PayPalPayoutGateway($cfg['paypal']['base'], $cfg['paypal']['client_id'], $cfg['paypal']['secret'])
                : new FakePayoutGateway;
        });
    }

    public function boot(): void
    {
        //
    }
}
