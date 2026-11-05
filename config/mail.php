<?php

return ['default' => env('MAIL_MAILER', 'log'), 'mailers' => [
    'log' => ['transport' => 'log', 'channel' => 'single'],
    'smtp' => ['transport' => 'smtp', 'scheme' => env('MAIL_SCHEME'), 'host' => env('MAIL_HOST', '127.0.0.1'), 'port' => env('MAIL_PORT', 2525), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD'), 'timeout' => 10],
], 'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@ostandari.test'), 'name' => env('APP_NAME', 'Ostandari')]];
