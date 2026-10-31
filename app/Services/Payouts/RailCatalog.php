<?php

namespace App\Services\Payouts;

/**
 * The payout-rail catalog, read from config('handlemyap.rails'). This is the
 * single source of truth for WHICH rails exist, which country/currency each
 * serves, and whether it's usable right now (platform creds present, or the
 * offline fake). Routing, Settings and the Vendors page all read from here, so
 * adding a country is: add a rail to config + a matching PayoutGateway.
 */
class RailCatalog
{
    /** @return array<string, array{provider: string, method: string, country: string, currency: string, planned?: bool}> */
    public function all(): array
    {
        return config('handlemyap.rails', []);
    }

    /** @return array{provider: string, method: string, country: string, currency: string, planned?: bool}|null */
    public function get(string $rail): ?array
    {
        return $this->all()[$rail] ?? null;
    }

    public function has(string $rail): bool
    {
        return $this->get($rail) !== null;
    }

    /** Rails that serve a given country (ISO-2), keyed by rail. */
    public function forCountry(string $country): array
    {
        $country = strtoupper($country);

        return array_filter($this->all(), fn ($meta) => strtoupper($meta['country']) === $country);
    }

    /** Country (ISO-2) a rail pays into, or null. */
    public function countryFor(string $rail): ?string
    {
        $meta = $this->get($rail);

        return $meta ? strtoupper($meta['country']) : null;
    }

    public function currencyFor(string $rail): ?string
    {
        $meta = $this->get($rail);

        return $meta ? strtoupper($meta['currency']) : null;
    }

    /** The country of the first rail whose currency matches, or null. */
    public function countryForCurrency(string $currency): ?string
    {
        $currency = strtoupper($currency);
        foreach ($this->all() as $meta) {
            if (strtoupper($meta['currency']) === $currency) {
                return strtoupper($meta['country']);
            }
        }

        return null;
    }

    /** Distinct supported countries (ISO-2), in catalog order. */
    public function countries(): array
    {
        return array_values(array_unique(array_map(
            fn ($meta) => strtoupper($meta['country']),
            $this->all(),
        )));
    }

    /** True once a rail has real platform credentials and can move money. */
    public function isLive(string $rail): bool
    {
        if ($this->get($rail)['planned'] ?? false) {
            return false;
        }

        return match ($rail) {
            'dwolla' => filled(config('handlemyap.dwolla.key')) && filled(config('handlemyap.dwolla.secret')),
            'paystack' => filled(config('handlemyap.paystack.secret')),
            default => false,
        };
    }

    /**
     * True if a rail can be selected/used now — live creds, OR the offline fake
     * is forced (gateway=fake) for demos/tests. Planned rails are never usable.
     */
    public function isUsable(string $rail): bool
    {
        if ($this->get($rail)['planned'] ?? false) {
            return false;
        }

        if (config('handlemyap.gateway') === 'fake') {
            return true;
        }

        return $this->isLive($rail);
    }

    /** A human label like "Bank transfer · Paystack". */
    public function label(string $rail): string
    {
        $meta = $this->get($rail);
        if (! $meta) {
            return ucfirst($rail);
        }

        return $meta['method'].' · '.$meta['provider'];
    }
}
