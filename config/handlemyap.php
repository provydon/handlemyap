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
    'gateway' => env('HANDLEMYAP_GATEWAY', 'auto'),     // auto | fake | dwolla

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('HANDLEMYAP_AI_MODEL', 'claude-sonnet-4-5'),
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
