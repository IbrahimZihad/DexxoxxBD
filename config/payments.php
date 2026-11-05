<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Payment rails. Every method here is manual-first: the customer sends money and
 * declares a reference, a human verifies it, and only OrderService::verifyPayment()
 * flips an order to paid. A card checkout is offered only when gateway
 * credentials actually exist, and even then the redirect back is never treated
 * as proof of payment.
 */
return [
    'currency' => 'BDT',

    'methods' => [
        'bkash' => [
            'label' => 'bKash',
            'kind' => 'wallet',
            'number_key' => 'payment.bkash_number',
            'number_label' => 'bKash personal number',
            'txn_label' => 'TrxID',
            'txn_pattern' => '/^[A-Za-z0-9]{6,20}$/',
            'txn_hint' => 'The 10-character TrxID printed on your bKash confirmation SMS.',
            'color' => '#e2136e',
            'instructions' => 'Send the exact total with "Send Money" or "Payment", then paste the TrxID below.',
            'available' => true,
        ],
        'nagad' => [
            'label' => 'Nagad',
            'kind' => 'wallet',
            'number_key' => 'payment.nagad_number',
            'number_label' => 'Nagad personal number',
            'txn_label' => 'TrxID',
            'txn_pattern' => '/^[A-Za-z0-9]{6,20}$/',
            'txn_hint' => 'The transaction ID from your Nagad receipt.',
            'color' => '#f6821f',
            'instructions' => 'Send the exact total to our Nagad number, then paste the TrxID below.',
            'available' => true,
        ],
        'rocket' => [
            'label' => 'Rocket',
            'kind' => 'wallet',
            'number_key' => 'payment.rocket_number',
            'number_label' => 'Rocket number',
            'txn_label' => 'Ref No',
            'txn_pattern' => '/^[A-Za-z0-9]{6,20}$/',
            'txn_hint' => 'Rocket reference number, usually 14 characters.',
            'color' => '#8c3494',
            'instructions' => 'Send the total to our Rocket account, then paste the reference number below.',
            'available' => true,
        ],
        'card' => [
            'label' => 'Card / SSLCommerz',
            'kind' => 'gateway',
            'number_key' => '',
            'number_label' => '',
            'txn_label' => 'Gateway reference',
            'txn_pattern' => '/^[A-Za-z0-9_-]{6,60}$/',
            'txn_hint' => 'The reference shown on your bank receipt or gateway confirmation.',
            'color' => '#0b5cff',
            'instructions' => 'Pay by card on the gateway, then paste the reference here so we can match it.',
            // Only advertised once real credentials exist in .env.
            'available' => Env::get('SSLCOMMERZ_STORE_ID', '') !== '' && Env::get('SSLCOMMERZ_STORE_PASSWORD', '') !== '',
        ],
        'bank' => [
            'label' => 'Bank transfer',
            'kind' => 'bank',
            'number_key' => 'payment.bank_account',
            'number_label' => 'Account details',
            'txn_label' => 'Deposit slip no',
            'txn_pattern' => '/^[A-Za-z0-9_-]{4,40}$/',
            'txn_hint' => 'The slip or transaction reference from your bank.',
            'color' => '#0f766e',
            'instructions' => 'Transfer to the account below; larger orders are usually settled this way.',
            'available' => true,
        ],
        'later' => [
            'label' => 'Pay after delivery',
            'kind' => 'manual',
            'number_key' => '',
            'number_label' => '',
            'txn_label' => '',
            'txn_pattern' => null,
            'txn_hint' => '',
            'color' => '#4f46e5',
            'instructions' => 'We prepare the order, a human checks your details on WhatsApp, and you pay when we hand it over.',
            'available' => true,
        ],
    ],

    // Reference validation for the wallet rails is format-only on purpose: the
    // amount and the TrxID are confirmed by staff against their own statement.
    'require_txn_for' => ['bkash', 'nagad', 'rocket', 'card', 'bank'],
];
