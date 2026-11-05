<?php

namespace App\Http\Middleware;

use App\Services\Workspace;
use Closure;
use Illuminate\Http\Request;

final class WebStaff
{
    public function handle(Request $request, Closure $next)
    {
        $login = '/panel/admin/login';
        if ($request->routeIs('county.admin')) {
            $county = app(\App\Services\PortalView::class)->county($request->route('slug'));
            abort_unless($county, 404);
            $login = '/county-admin/'.$county['slug'].'/login';
        }
        if (!$request->user()) return redirect($login.'?error=auth_required&return='.rawurlencode($request->getRequestUri()));
        abort_unless($request->user()->is_active && $request->user()->isStaff(), 403, 'این حساب به پنل مدیریت دسترسی ندارد.');
        abort_if($request->user()->isStaffManager(), 403, 'از پنل اختصاصی صفحهٔ خود وارد شوید.');
        Workspace::assertUser($request->user());
        if ($request->routeIs('county.admin')) Workspace::assertCounty($request->user(), $county['slug']);
        return $next($request);
    }
}
