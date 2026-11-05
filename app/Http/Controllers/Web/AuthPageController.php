<?php

namespace App\Http\Controllers\Web;

use App\Services\PortalView;
use App\Services\Workspace;
use Illuminate\Http\Request;

final class AuthPageController
{
    public function login(Request $request) { return $this->page($request, 'login'); }
    public function register(Request $request) { return $this->page($request, 'register'); }
    public function staff(Request $request) { return $this->page($request, 'staff'); }
    public function county(Request $request, string $slug, PortalView $ui)
    {
        $county = $ui->county($slug);
        abort_unless($county, 404);
        return $this->page($request, 'staff', $county);
    }

    private function page(Request $request, string $mode, ?array $county = null)
    {
        return view('auth.login', ['title' => $county ? 'ورود روابط عمومی فرمانداری '.$county['name'] : ($mode==='staff' ? 'ورود به پنل مدیریت' : ($mode==='register' ? 'ثبت‌نام شهروندان' : 'ورود شهروندان')), 'mode'=>$mode, 'county'=>$county]);
    }

    public function citizen(Request $request, PortalView $ui)
    {
        if (!config('website.citizen_demo') && !$request->user()) return redirect('/login?return=%2Fcitizen');
        if ($request->user() && $request->user()->isStaff()) return redirect(Workspace::context($request->user())['dashboard_path']);
        abort_if($request->user() && !$request->user()->is_active, 403);
        $page = collect($ui->data('frontend')['templates'])->firstWhere('path', '/citizen');
        return view('citizen.dashboard', ['page'=>$page, 'title'=>'پنل خدمات مردمی', 'path'=>'/citizen']);
    }
}
