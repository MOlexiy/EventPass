<?php

return [

    /*
    | Comma-separated list of gateways shown to buyers. "fake" is a local
    | simulator so the whole purchase flow works without merchant keys.
    */
    'enabled' => array_filter(explode(',', (string) env('PAYMENT_GATEWAYS', 'fake'))),

    // How long a pending order keeps its seats reserved.
    'hold_minutes' => (int) env('ORDER_HOLD_MINUTES', 15),

    // Where the SPA lives: used for redirect URLs after checkout.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // Public URL providers can reach for webhooks (e.g. an ngrok tunnel).
    'webhook_base_url' => env('WEBHOOK_BASE_URL', env('APP_URL')),

    'liqpay' => [
        'public_key' => env('LIQPAY_PUBLIC_KEY'),
        'private_key' => env('LIQPAY_PRIVATE_KEY'),
        'checkout_url' => 'https://www.liqpay.ua/api/3/checkout',
        'api_url' => 'https://www.liqpay.ua/api/request',
        'sandbox' => (bool) env('LIQPAY_SANDBOX', true),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_url' => 'https://api.stripe.com/v1',
        // Max age of a signed webhook, protects against replayed deliveries.
        'tolerance' => 300,
    ],

];
