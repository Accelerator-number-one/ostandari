<?php

return ['paths' => ['api/*', 'up'], 'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], 'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))), 'allowed_origins_patterns' => [], 'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'], 'exposed_headers' => ['Content-Disposition'], 'max_age' => 600, 'supports_credentials' => false];
