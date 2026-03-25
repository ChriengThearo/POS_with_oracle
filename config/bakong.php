<?php

return [
    'token' => env('BAKONG_TOKEN'),
    'merchant_account' => env('BAKONG_MERCHANT_ACCOUNT'),
    'merchant_name' => env('BAKONG_MERCHANT_NAME', 'My Store'),
    'merchant_id' => env('BAKONG_MERCHANT_ID', '123456'),
    'acquiring_bank' => env('BAKONG_ACQUIRING_BANK', 'Your Bank'),
    'city' => env('BAKONG_MERCHANT_CITY', 'Phnom Penh'),
    'store_label' => env('BAKONG_STORE_LABEL', 'WEBSTORE'),
    'terminal_label' => env('BAKONG_TERMINAL_LABEL', 'ONLINE'),
];
