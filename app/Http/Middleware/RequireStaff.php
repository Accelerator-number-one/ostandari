<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireStaff
{
    public function handle(Request $r, Closure $next)
    {
        abort_unless($r->user()?->isStaff(), 403);
        abort_if($r->user()->isStaffManager(), 403, 'از پنل اختصاصی صفحهٔ خود استفاده کنید.');

        return $next($r);
    }
}
