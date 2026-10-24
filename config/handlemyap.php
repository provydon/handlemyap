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
    'gateway' => env('HANDLEMYAP_GATEWAY', 'auto'),     // auto | fake | paypal

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('HANDLEMYAP_AI_MODEL', 'claude-sonnet-4-5'),
    ],

    'paypal' => [
        'base' => env('PAYPAL_BASE', 'https://api-m.sandbox.paypal.com'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
    ],
];
