<?php

/*
|--------------------------------------------------------------------------
| ABA PayWay
|--------------------------------------------------------------------------
| Card / ABA KHQR payments for courses. Merchant ID and API key come from ABA
| (sandbox: https://sandbox.payway.com.kh/register-sandbox/). Use the sandbox
| base URL until ABA gives you the production URL and keys.
|
| Without credentials, local development uses a built-in test payment page so
| the whole flow can be tried; it is never used in production.
*/

return [

    // Online payment before an admin has used the switch in Settings > Payments: off.
    // (The test suite turns it on, so the payment tests run as on a live shop.)
    'online_by_default' => (bool) env('ONLINE_PAYMENT_DEFAULT', false),

    'merchant_id' => env('PAYWAY_MERCHANT_ID'),
    'api_key' => env('PAYWAY_API_KEY'),
    'base_url' => rtrim(env('PAYWAY_BASE_URL', 'https://checkout-sandbox.payway.com.kh'), '/'),
    'currency' => env('PAYWAY_CURRENCY', 'USD'),

    // Minutes the PayWay payment page stays valid (PayWay minimum: 3).
    'lifetime' => (int) env('PAYWAY_LIFETIME', 30),

    // true = always use the built-in test payment page (local/testing only).
    'test_gateway' => env('PAYWAY_TEST_GATEWAY'),
];
