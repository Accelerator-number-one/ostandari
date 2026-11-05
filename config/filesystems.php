<?php

return ['default' => env('FILESYSTEM_DISK', 'local'), 'disks' => [
    'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true],
    'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => rtrim((string) env('APP_URL'), '/').'/storage', 'visibility' => 'public', 'throw' => true],
    'portal-icons' => ['driver' => 'local', 'root' => public_path('design/icons'), 'url' => '/design/icons', 'visibility' => 'public', 'throw' => true],
], 'links' => [public_path('storage') => storage_path('app/public')]];
