<?php

return [
    'name' => env('APP_NAME', 'Ostandari API'), 'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false), 'url' => env('APP_URL', 'http://127.0.0.1:8000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Tehran'), 'locale' => env('APP_LOCALE', 'fa'),
    'fallback_locale' => 'en', 'faker_locale' => 'fa_IR', 'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    'maintenance' => ['driver' => env('APP_MAINTENANCE_DRIVER', 'file')],
];
