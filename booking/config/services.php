<?php

return [
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'webhook_url' => env('SMS_WEBHOOK_URL'),
        'webhook_token' => env('SMS_WEBHOOK_TOKEN'),
        'sender_id' => env('SMS_SENDER_ID', 'HOTELVASTU'),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'api_base' => env('RAZORPAY_API_BASE', 'https://api.razorpay.com/v1'),
    ],
];
