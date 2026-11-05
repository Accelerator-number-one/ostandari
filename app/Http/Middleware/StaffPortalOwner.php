<?php

namespace App\Http\Middleware;

use App\Services\StaffPortals;
use Closure;
use Illuminate\Http\Request;

final class StaffPortalOwner
{
    public function handle(Request $request, Closure $next)
    {
        $portal = StaffPortals::resolve($request->route('slug'));
        if (!$request->user()) {
            if ($request->expectsJson()) abort(401, 'ابتدا وارد پنل همین صفحه شوید.');
            return redirect($portal->adminPath().'/login?error=auth_required');
        }
        StaffPortals::assertOwner($request->user(), $portal);
        $request->attributes->set('staff_portal', $portal);
        return $next($request);
    }
}
