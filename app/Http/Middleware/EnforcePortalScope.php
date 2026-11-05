<?php

namespace App\Http\Middleware;

use App\Services\Workspace;
use Closure;
use Illuminate\Http\Request;

final class EnforcePortalScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->isStaff()) {
            abort_if($user->isStaffManager(), 403, 'این مسیر متعلق به پنل اختصاصی مدیر نیست.');
            Workspace::assertUser($user);
            abort_if(! $request->isMethodSafe() && $user->hasReadOnlyWorkspace() &&
                ! \App\Services\ManagerPages::mayWrite($request) &&
                ! ($request->routeIs('admin.annual-slogan.update', 'web.admin.annual-slogan.update') && Workspace::mayManageAnnualSlogan($user)) &&
                ! ($request->routeIs('admin.custom-pages.*', 'web.admin.custom-pages.*') && \App\Services\CustomPages::mayManage($user)), 403, 'پنل روابط عمومی استانداری فقط‌خواندنی است.');
        }
        return $next($request);
    }
}
