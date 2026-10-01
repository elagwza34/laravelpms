<?php

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('API_ALLOWED_ORIGINS', '')),
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | The frontend is a separate React + TypeScript application, so every API
    | response has to opt in to cross-origin requests. Allowed origins are
    | driven by the API_ALLOWED_ORIGINS environment variable, which accepts a
    | comma separated list of exact origins, or "*" to allow any origin.
    |
    | Credentialed requests (Sanctum SPA cookie auth) must never be combined
    | with the "*" wildcard. In that case list each allowed origin explicitly.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('API_CORS_SUPPORTS_CREDENTIALS', false),

];
