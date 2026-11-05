<?php

namespace App\Http\Controllers\Api;

use App\Services\{CountyPortal, PortalView, Workspace};
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CountyPortalController
{
    public function show(Request $request)
    {
        Workspace::assertCounty($request->user());
        abort_unless($request->user()->hasPermission('organizations.manage'), 403);
        return Api::ok(CountyPortal::form($request->user()));
    }

    public function update(Request $request)
    {
        return Api::ok(CountyPortal::save($request->user(), $request->all()), 'تنظیمات پرتال همین شهرستان ذخیره شد.');
    }

    public function menu(Request $request, PortalView $ui)
    {
        $data = $request->validate(['group'=>['required', Rule::in(array_keys(CountyPortal::MENUS))]]);
        return Api::ok(CountyPortal::prepareMenu($request->user(), $data['group'], $ui));
    }
}
