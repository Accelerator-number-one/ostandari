<?php

namespace App\Http\Controllers\Web;

use App\Models\StaffPortalPage;
use App\Services\{Audit, StaffPages};
use Illuminate\Http\Request;

final class StaffPageController
{
    public function editor(Request $request, string $slug, ?int $id = null)
    {
        abort_unless(StaffPages::ready(), 503, 'آماده‌سازی صفحات در حال انجام است.');
        $portal = $request->attributes->get('staff_portal');
        $page = $id ? $portal->pages()->findOrFail($id) : new StaffPortalPage(['status' => 'draft', 'priority' => 0, 'show_in_navigation' => false]);
        return view('staff.page-editor', ['portal' => $portal, 'staffPage' => $page, 'tab' => 'pages', 'title' => $id ? 'ویرایش صفحه' : 'ساخت صفحه جدید']);
    }

    public function save(Request $request, string $slug, ?int $id = null)
    {
        abort_unless(StaffPages::ready(), 503, 'آماده‌سازی صفحات در حال انجام است.');
        $portal = $request->attributes->get('staff_portal');
        $page = $id ? $portal->pages()->findOrFail($id) : null;
        StaffPages::save($portal, $request->except('_token'), $page);
        return redirect($portal->adminPath().'?tab=pages')->with('status', 'صفحه ذخیره شد.');
    }

    public function remove(Request $request, string $slug, int $id)
    {
        $portal = $request->attributes->get('staff_portal');
        $page = $portal->pages()->findOrFail($id);
        $page->delete();
        Audit::record('staff-portal.page-deleted', $page);
        return redirect($portal->adminPath().'?tab=pages')->with('status', 'صفحه به زباله‌دان منتقل شد.');
    }

    public function restore(Request $request, string $slug, int $id)
    {
        $portal = $request->attributes->get('staff_portal');
        $page = $portal->pages()->onlyTrashed()->findOrFail($id);
        $page->restore();
        Audit::record('staff-portal.page-restored', $page);
        return redirect($portal->adminPath().'?tab=pages')->with('status', 'صفحه بازیابی شد.');
    }
}
