<?php

return ['stateful' => [], 'guard' => ['web'], 'expiration' => (int) env('AUTH_TOKEN_MINUTES', 1440), 'token_prefix' => '', 'middleware' => []];
