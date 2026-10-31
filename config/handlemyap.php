<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Extractor + gateway selection
    |--------------------------------------------------------------------------
    | 'auto' picks the real implementation when its credentials are present,
    | otherwise the offline stub/fake — so the app runs (and tests pass) with
    | zero configuration, and lights up the moment you add keys.
    */
    'extractor' => env('HANDLEMYAP_EXTRACTOR', 'auto'), // auto | stub | ai
    'gateway' => env('HANDLEMYAP_GATEWAY', 'auto'),     // auto | fake — 'fake' forces the offline rail everywhere

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('HANDLEMYAP_AI_MODEL', 'claude-sonnet-4-5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payout rails — the catalog (payment-method agnostic routing)
    |--------------------------------------------------------------------------
    | A payout is routed to a rail by the VENDOR'S COUNTRY, not by a global
    | gateway choice. Each rail serves one country/currency; a country may
    | expose several rails, and the business picks which one to use for that
    | country (User::$payout_routes). Add a country by adding a rail here and a
    | matching PayoutGateway — the router, Settings and Vendors pages are all
    | driven by this catalog, so no other code changes.
    |
    | 'planned' => true marks a rail whose gateway isn't wired yet: it shows in
    | the UI as "coming soon" and never routes real money.
    */
    'rails' => [
        'dwolla' => [
            'provider' => 'Dwolla',
            'method' => 'Bank transfer · ACH',
            'country' => 'US',
            'currency' => 'USD',
        ],
        'paystack' => [
            'provider' => 'Paystack',
            'method' => 'Bank transfer',
            'country' => 'NG',
            'currency' => 'NGN',
        ],
        // The UK can be paid two ways — the business picks in Settings.
        'wise' => [
            'provider' => 'Wise',
            'method' => 'Bank transfer',
            'country' => 'GB',
            'currency' => 'GBP',
        ],
        'stripe' => [
            'provider' => 'Stripe',
            'method' => 'Global Payouts',
            'country' => 'GB',
            'currency' => 'GBP',
        ],
    ],

    'paystack' => [
        // Platform-level Paystack secret (one account for everyone). test vs
        // live is decided by the key prefix (sk_test_ / sk_live_), not a host.
        'secret' => env('PAYSTACK_SECRET'),
    ],

    'wise' => [
        // Platform Wise account: a scoped API token + the business profile id
        // money is sent from. Sandbox vs live is a host switch.
        'token' => env('WISE_API_TOKEN'),
        'profile_id' => env('WISE_PROFILE_ID'),
        'env' => env('WISE_ENV', 'sandbox'), // sandbox | live
    ],

    'stripe' => [
        // Stripe Global Payouts (OutboundPayments v2). A restricted key, the
        // financial account money is sent from, and the pinned preview version.
        'secret' => env('STRIPE_SECRET'),
        'financial_account' => env('STRIPE_FINANCIAL_ACCOUNT'),
        'version' => env('STRIPE_GLOBAL_PAYOUTS_VERSION', '2026-09-30.preview'),
    ],

    'dwolla' => [
        // Platform-level Dwolla app credentials (one app for everyone). Per-
        // business/vendor banks are funding sources, not separate API keys.
        'key' => env('DWOLLA_KEY'),
        'secret' => env('DWOLLA_SECRET'),
        'env' => env('DWOLLA_ENV', 'sandbox'), // sandbox | production
        // Default source bank a business pays from, unless they connect their own.
        'source_funding_source' => env('DWOLLA_FUNDING_SOURCE'),
    ],

    'plaid' => [
        // Used for instant bank verification — turns a connected business bank
        // into a Dwolla-verified funding source that can actually SEND.
        'client_id' => env('PLAID_CLIENT_ID'),
        'secret' => env('PLAID_SECRET'),
        'env' => env('PLAID_ENV', 'sandbox'), // sandbox | development | production
    ],
];
