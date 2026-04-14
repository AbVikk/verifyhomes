<?php

return [
    'platform_fee_percentage' => (float) env('PLATFORM_FEE_PERCENTAGE', 10),
    'rent_platform_fee_percentage' => (float) env('RENT_PLATFORM_FEE_PERCENTAGE', 20),
    'terms_gate_seconds' => (int) env('TERMS_GATE_SECONDS', 10),
    'default_provider' => env(
        'PAYMENT_PROVIDER',
        env('PAYSTACK_SECRET_KEY') ? 'paystack' : 'stub',
    ),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', 'verifyhomes-test-secret'),
    'providers' => [
        'stub' => [
            'label' => 'Stub Gateway',
            'webhook_signature_header' => 'X-Verifyhomes-Signature',
        ],
        'paystack' => [
            'label' => 'Paystack',
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'verify_ssl' => env('PAYSTACK_VERIFY_SSL', true),
            'webhook_signature_header' => 'X-Paystack-Signature',
        ],
    ],
    'transaction_amounts' => [
        'inspection_booking_fee' => (float) env('INSPECTION_BOOKING_FEE_AMOUNT', 5000),
    ],
];
