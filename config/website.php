<?php

return [
    'citizen_demo' => (bool) env('CITIZEN_DEMO', false),
    'snapshot' => (bool) env('PORTAL_SNAPSHOT', false),
    // Optional, one-time provisioning; only a password hash is accepted. No default credentials.
    'staff_demo_hash' => env('STAFF_PORTAL_DEMO_PASSWORD_HASH'),
    'staff_demo_username' => env('STAFF_PORTAL_DEMO_USERNAME', 'staff_it_test'),
];
