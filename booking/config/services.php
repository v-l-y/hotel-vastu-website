<?php

return [
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'api_base' => env('RAZORPAY_API_BASE', 'https://api.razorpay.com/v1'),
    ],
];
