<?php

return [
    'endpoint' => env('ADVANTA_SMS_URL', 'https://quicksms.advantasms.com/api/services/sendsms/'),
    'api_key' => env('ADVANTA_API_KEY'),
    'partner_id' => env('ADVANTA_PARTNER_ID'),
    'sender_id' => env('ADVANTA_SENDER_ID'),
];
