<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_active, 403, 'حساب کاربری غیرفعال است.');

        return $next($request);
    }
}
