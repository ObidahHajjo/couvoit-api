<?php

return [
    'secret' => env('JWT_SECRET'),
    'issuer' => env('JWT_ISSUER', 'couvoit-api'),
    'audience' => env('JWT_AUDIENCE', 'couvoit-client'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),       // seconds
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000), // seconds
];
