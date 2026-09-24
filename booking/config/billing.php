<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Official billing identity
    |--------------------------------------------------------------------------
    |
    | Keep the hotel's real legal/tax identity in environment configuration.
    | The UI only labels a document as a GST Tax Invoice when a valid GSTIN is
    | configured. Never add demo registration details to production.
    |
    */
    'legal_name' => env('BILLING_LEGAL_NAME', 'Hotel Vastu Premium'),
    'trade_name' => env('BILLING_TRADE_NAME', 'Hotel Vastu Premium'),
    'address' => env('BILLING_ADDRESS'),
    'state' => env('BILLING_STATE'),
    'state_code' => env('BILLING_STATE_CODE'),
    'gstin' => env('BILLING_GSTIN'),
    'phone' => env('BILLING_PHONE'),
    'email' => env('BILLING_EMAIL'),
    'authorised_signatory' => env('BILLING_AUTHORISED_SIGNATORY'),

    // Hotel accommodation and restaurant services sit under Heading 9963.
    // Override these with the exact SAC used by the hotel's tax adviser.
    'sac_accommodation' => env('BILLING_SAC_ACCOMMODATION', '9963'),
    'sac_restaurant' => env('BILLING_SAC_RESTAURANT', '9963'),

    'series' => [
        'hotel_invoice' => 'HV',
        'restaurant_invoice' => 'HR',
        'credit_note' => 'HC',
    ],
];
