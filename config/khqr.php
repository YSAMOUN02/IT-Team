<?php

/*
|--------------------------------------------------------------------------
| KHQR (Bakong)
|--------------------------------------------------------------------------
| The receiving account, name and city are in Admin > Settings > KHQR payment.
| BAKONG_TOKEN (free, from https://api-bakong.nbc.gov.kh/register) lets the site
| see payments arrive by itself; without it an admin confirms each KHQR order.
*/

return [
    'token' => env('BAKONG_TOKEN'),
    'api_url' => env('BAKONG_API_URL', 'https://api-bakong.nbc.gov.kh'),
];
