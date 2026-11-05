<?php

namespace App\Http\Controllers\Api;

use App\Models\{CustomPage, Media};
use App\Services\{Audit, CustomPages, MediaUploadPreparer};
use App\Support\Api;
use Illuminate\Http\Request;

final class CustomPageController
{
    private function query(Request $request)
    {
        $org = CustomPages::organization($request->user());
        abort_unless(CustomPages::ready(), 503, 'صفحه‌ساز در حال آماده‌سازی است؛ چند لحظه دیگر تلاش کنید.');
        return CustomPage::where('organization_id', $org->id)->with('linkImage');
    }

    public function index(Request $request)
    {
        $query = $this->query($request);
        $request->validate(['q' => 'nullable|string|max:200', 'page' => 'sometimes|integer|min:1', 'trashed' => 'sometimes|boolean']);
        if ($request->boolean('trashed')) $query->onlyTrashed();
        if ($request->filled('q')) $query->where(fn ($q) => $q->where('title','like','%'.$request->string('q').'%')->orWhere('path','like','%'.$request->string('q').'%'));
        return Api::page($query->latest('id')->paginate(20));
    }

    public function show(Request $request, int $id) { return Api::ok($this->query($request)->findOrFail($id)); }
    public function store(Request $request) { $this->query($request); return Api::ok(CustomPages::save($request->user(), $request->all()), 'صفحه ذخیره شد.', 201); }
    public function update(Request $request, int $id) { return Api::ok(CustomPages::save($request->user(), $request->all(), $this->query($request)->findOrFail($id)), 'صفحه به‌روز شد.'); }
    public function destroy(Request $request, int $id)
    {
        $page = $this->query($request)->findOrFail($id);
        $page->delete(); Audit::record('custom-pages.deleted', $page);
        return response()->noContent();
    }
    public function restore(Request $request, int $id)
    {
        $page = $this->query($request)->onlyTrashed()->findOrFail($id);
        $page->restore(); Audit::record('custom-pages.restored', $page);
        return Api::ok($page);
    }
    public function images(Request $request)
    {
        $org = CustomPages::organization($request->user());
        $request->validate(['q' => 'nullable|string|max:100', 'per_page' => 'sometimes|integer|min:1|max:100']);
        $query = Media::where('organization_id', $org->id)->where('visibility','public')->where('mime','like','image/%');
        if ($request->filled('q')) $query->where('original_name','like','%'.$request->string('q').'%');
        return Api::page($query->latest('id')->paginate($request->integer('per_page', 30)));
    }
    public function image(Request $request, MediaUploadPreparer $uploads)
    {
        $org = CustomPages::organization($request->user());
        abort_unless($request->user()->hasPermission('media.manage'), 403);
        abort_unless(CustomPages::ready(), 503);
        $request->validate(['file' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:'.config('portal.upload_max_kb'), 'alt'=>'nullable|string|max:255']);
        return Api::ok(\App\Services\PageImages::store($request->user(), $request->file('file'), $uploads, $request->input('alt')), 'تصویر بارگذاری شد.', 201);
    }
}
