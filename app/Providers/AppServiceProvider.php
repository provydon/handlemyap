<?php

namespace App\Providers;

use App\Services\Extraction\AiInvoiceExtractor;
use App\Services\Extraction\InvoiceExtractor;
use App\Services\Extraction\StubExtractor;
use App\Services\Payouts\DwollaConnector;
use App\Services\Payouts\DwollaGateway;
use App\Services\Payouts\FakePayoutGateway;
use App\Services\Payouts\PayoutGateway;
use App\Services\Payouts\PlaidConnector;
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

        // The default/global gateway. Per-user resolution happens in
        // PayoutGatewayResolver; this binding is the fallback (and what the
        // resolver returns when gateway=fake).
        $this->app->bind(PayoutGateway::class, function ($app): PayoutGateway {
            $cfg = $app['config']['handlemyap'];
            $useDwolla = $cfg['gateway'] === 'dwolla'
                || ($cfg['gateway'] === 'auto' && filled($cfg['dwolla']['key']) && filled($cfg['dwolla']['secret']));

            return $useDwolla
                ? new DwollaGateway($cfg['dwolla']['key'], $cfg['dwolla']['secret'], $cfg['dwolla']['env'], $cfg['dwolla']['source_funding_source'])
                : new FakePayoutGateway;
        });

        // The onboarding connectors need the platform creds (strings) to autowire.
        $this->app->bind(DwollaConnector::class, fn ($app) => new DwollaConnector(
            (string) ($app['config']['handlemyap.dwolla.key'] ?? ''),
            (string) ($app['config']['handlemyap.dwolla.secret'] ?? ''),
            (string) ($app['config']['handlemyap.dwolla.env'] ?? 'sandbox'),
        ));

        $this->app->bind(PlaidConnector::class, fn ($app) => new PlaidConnector(
            (string) ($app['config']['handlemyap.plaid.client_id'] ?? ''),
            (string) ($app['config']['handlemyap.plaid.secret'] ?? ''),
            (string) ($app['config']['handlemyap.plaid.env'] ?? 'sandbox'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
