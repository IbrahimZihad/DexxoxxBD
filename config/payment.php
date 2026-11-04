<?php

declare(strict_types=1);

return [
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'mock'),

    'currency' => env('DEFAULT_CURRENCY', 'BDT'),

    // Seconds an unpaid order stays reserved before the cron releases stock.
    'pending_order_ttl' => 3600,

    'methods' => [
        'mock' => [
            'label' => 'Demo Gateway (sandbox)',
            'description' => 'Simulates a full hosted-checkout flow for local testing.',
            'enabled' => filter_var(env('PAYMENT_MOCK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'instructions' => 'A fake payment page opens after you place the order. '
                . 'Choose success, failure or pending to test each path.',
            'icon' => 'credit-card',
            'sort' => 1,
            'fee_type' => 'flat',
            'fee' => 0,
        ],
        'bkash' => [
            'label' => 'bKash',
            'description' => 'Pay with your bKash wallet or card through bKash.',
            'enabled' => filter_var(env('PAYMENT_BKASH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'instructions' => 'You will be redirected to bKash to authorise the payment. '
                . 'The transaction ID is verified on our side before your order is confirmed.',
            'icon' => 'wallet2',
            'sort' => 2,
            'fee_type' => 'flat',
            'fee' => 0,
        ],
        'nagad' => [
            'label' => 'Nagad',
            'description' => 'Pay with your Nagad account.',
            'enabled' => filter_var(env('PAYMENT_NAGAD_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'instructions' => 'Complete the payment on Nagad’s secure page. '
                . 'We confirm it from the notification Nagad sends us.',
            'icon' => 'smartphone',
            'sort' => 3,
            'fee_type' => 'flat',
            'fee' => 0,
        ],
        'sslcommerz' => [
            'label' => 'Card / SSLCommerz',
            'description' => 'Visa, Mastercard, Amex, DBBL and other local cards.',
            'enabled' => filter_var(env('PAYMENT_SSLCOMMERZ_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'instructions' => 'Card details are entered only on the SSLCommerz page. '
                . 'We never see or store your card number.',
            'icon' => 'credit-card',
            'sort' => 4,
            'fee_type' => 'percent',
            'fee' => 2.0,
        ],
        'bank_transfer' => [
            'label' => 'Bank transfer',
            'description' => 'Send a bank transfer and submit the reference.',
            'enabled' => filter_var(env('PAYMENT_BANK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'instructions' => 'Transfer to the account shown after checkout, then submit the transaction reference. '
                . 'Orders are delivered once our team verifies the deposit.',
            'icon' => 'landmark',
            'sort' => 5,
            'fee_type' => 'flat',
            'fee' => 0,
            'account' => [
                'bank' => env('BANK_NAME', 'City Bank'),
                'branch' => env('BANK_BRANCH', 'Gulshan, Dhaka'),
                'account_name' => env('BANK_ACCOUNT_NAME', 'DexxoxxBD Digital Services'),
                'account_number' => env('BANK_ACCOUNT_NUMBER', '1234567890123'),
                'routing' => env('BANK_ROUTING', '220137542'),
            ],
        ],
        'cash_on_delivery' => [
            'label' => 'Manual / office payment',
            'description' => 'Pay in cash at our office when the order is processed.',
            'enabled' => filter_var(env('PAYMENT_MANUAL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'instructions' => 'Our team contacts you to arrange payment and delivery. '
                . 'Nothing is released until the payment is marked verified.',
            'icon' => 'banknote',
            'sort' => 6,
            'fee_type' => 'flat',
            'fee' => 0,
        ],
    ],

    'gateways' => [
        'sslcommerz' => [
            'store_id' => env('SSLCOMMERZ_STORE_ID', ''),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', ''),
            'sandbox' => filter_var(env('SSLCOMMERZ_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
            'base_url' => 'https://sandbox.sslcommerz.com',
            'live_url' => 'https://securepay.sslcommerz.com',
            'api_version' => 'v1',
        ],
        'bkash' => [
            'app_key' => env('BKASH_APP_KEY', ''),
            'app_secret' => env('BKASH_APP_SECRET', ''),
            'username' => env('BKASH_USERNAME', ''),
            'password' => env('BKASH_PASSWORD', ''),
            'sandbox' => filter_var(env('BKASH_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
            'base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'live_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
        ],
        'nagad' => [
            'merchant_id' => env('NAGAD_MERCHANT_ID', ''),
            'public_key' => env('NAGAD_PUBLIC_KEY', ''),
            'private_key' => env('NAGAD_PRIVATE_KEY', ''),
            'base_url' => env('NAGAD_BASE_URL', 'https://sandbox.nagad.com:20006/api/dp/dpscp-merchant-payment'),
        ],
        'mock' => [
            // The mock gateway is entirely local: it never leaves the server.
            'simulate_latency_ms' => (int) env('MOCK_GATEWAY_LATENCY_MS', 400),
        ],
    ],

    'webhook_token' => env('PAYMENT_WEBHOOK_TOKEN', ''),

    'refunds' => [
        'window_days' => (int) env('REFUND_WINDOW_DAYS', 7),
        'allow_partial' => true,
    ],
];
