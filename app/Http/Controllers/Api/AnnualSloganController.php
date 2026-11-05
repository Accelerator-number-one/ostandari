<?php

namespace App\Http\Controllers\Api;

use App\Models\Media;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\MediaUploadPreparer;
use App\Services\Workspace;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AnnualSloganController
{
    public function show(Request $request)
    {
        abort_unless(Workspace::mayManageAnnualSlogan($request->user()), 403);
        return Api::ok(Setting::where('key', 'site.slogan')->value('value'));
    }

    public function update(Request $request, MediaUploadPreparer $uploads)
    {
        abort_unless(Workspace::mayManageAnnualSlogan($request->user()), 403);
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('portal.upload_max_kb')]]);
        $upload = $uploads->prepare($request->file('file'));
        $path = now()->format('Y/m').'/'.Str::uuid().'.'.$upload->extension;
        $stream = null;
        try {
            $stream = fopen($upload->path, 'rb');
            abort_if($stream === false, 503, 'خواندن تصویر ممکن نیست.');
            abort_unless(Storage::disk('public')->put($path, $stream), 503, 'ذخیره تصویر انجام نشد.');
            $media = DB::transaction(function () use ($request, $upload, $path) {
                $media = Media::create(['user_id' => $request->user()->id, 'organization_id' => $request->user()->organization_id,
                    'disk' => 'public', 'path' => $path, 'original_name' => $upload->name, 'mime' => $upload->mime,
                    'size' => $upload->size, 'width' => $upload->width, 'height' => $upload->height,
                    'visibility' => 'public', 'alt' => 'شعار سال']);
                Setting::updateOrCreate(['key' => 'site.slogan'], ['value' => ['id' => $media->id, 'url' => $media->url], 'is_public' => true]);
                Audit::record('annual-slogan.updated', $media);
                return $media;
            });
            return Api::ok(['id' => $media->id, 'url' => $media->url], 'تصویر شعار سال جایگزین شد.');
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($path);
            throw $error;
        } finally {
            if (is_resource($stream)) fclose($stream);
            $upload->cleanup();
        }
    }
}
