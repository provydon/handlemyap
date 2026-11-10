<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Extractor + payment mode
    |--------------------------------------------------------------------------
    | 'auto' picks the real implementation when its credentials are present,
    | otherwise the offline stub — so the app runs (and tests pass) with zero
    | configuration, and lights up the moment you add keys.
    |
    | 'gateway' => 'fake' forces offline/demo mode everywhere: the "Pay with
    | PayPal" button simulates a capture instead of calling PayPal, so the whole
    | flow is demoable with no credentials.
    */
    'extractor' => env('HANDLEMYAP_EXTRACTOR', 'auto'), // auto | stub | ai
    'gateway' => env('HANDLEMYAP_GATEWAY', 'auto'),     // auto | fake

    'ai' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('HANDLEMYAP_AI_MODEL', 'claude-sonnet-4-5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal Checkout — the "Pay with PayPal" button
    |--------------------------------------------------------------------------
    | One platform-level PayPal REST app (client id + secret) opens the checkout.
    | The payer logs in to THEIR OWN PayPal in the popup and pays from their own
    | balance/card; the vendor (their PayPal email on the Vendors page) is the
    | payee. Nothing is funded from a platform balance. Sandbox vs live is a host
    | switch.
    */
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'env' => env('PAYPAL_ENV', 'sandbox'), // sandbox | live
    ],
];
