<?php

namespace App\Http\Controllers\Api;

use App\Models\Media;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\FileAccess;
use App\Services\MediaUploadPreparer;
use App\Services\PublicImages;
use App\Services\PrivateFiles;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaController
{
    public function index(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'visibility' => 'sometimes|in:public,private', 'kind' => 'sometimes|in:image,file', 'q' => 'sometimes|string|max:100']);
        $q = Media::query();
        if ($r->user()->hasPermission('media.manage') && ! $r->user()->hasGlobalAccess()) {
            $q->where(fn ($q) => $q->where(fn ($q) => $q->where('user_id', $r->user()->id)->where('organization_id', $r->user()->organization_id))->orWhere(fn ($q) => $q->where('organization_id', $r->user()->organization_id)->where('visibility', 'public')));
        } elseif (! $r->user()->hasPermission('media.manage')) {
            $q->where('user_id', $r->user()->id);
        }
        if ($r->filled('visibility')) {
            $q->where('visibility', $r->input('visibility'));
        }

        if ($r->filled('kind')) {
            $q->where('mime', $r->input('kind') === 'image' ? 'like' : 'not like', 'image/%');
        }
        if ($r->filled('q')) {
            $term = '%'.$r->input('q').'%';
            $q->where(fn ($q) => $q->where('original_name', 'like', $term)->orWhere('alt', 'like', $term));
        }

        return Api::page($q->latest('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function store(Request $r, MediaUploadPreparer $uploads)
    {
        $d = $r->validate(['file' => ['required', 'file', 'max:'.config('portal.upload_max_kb'), 'mimes:jpg,jpeg,png,webp,pdf,docx,xlsx,mp4,mp3,txt'],
            'visibility' => ['required', Rule::in(['public', 'private'])], 'alt' => 'nullable|string|max:255']);
        if ($d['visibility'] === 'public') {
            abort_unless($r->user()->isStaff() && $r->user()->hasPermission('media.manage'), 403);
        }
        $disk = $d['visibility'] === 'public' ? 'public' : 'local';
        $upload = $uploads->prepare($r->file('file'));
        $filePath = now()->format('Y/m').'/'.Str::uuid().'.'.$upload->extension;
        $stream = null;
        try {
            $persistent = $disk === 'public' && in_array($upload->mime, PublicImages::MIMES, true);
            $private = $disk === 'local' && $d['visibility'] === 'private';
            if ($persistent) PublicImages::assertReady();
            if ($private) PrivateFiles::assertReady();
            $stream = fopen($upload->path, 'rb');
            abort_if($stream === false, 503, 'خواندن فایل آپلودشده ممکن نیست.');
            abort_unless(Storage::disk($disk)->put($filePath, $stream), 503, 'ذخیره فایل انجام نشد. دوباره تلاش کنید.');
            $media = DB::transaction(function () use ($r, $disk, $filePath, $upload, $d, $persistent, $private) {
                $media = Media::create(['user_id' => $r->user()->id, 'organization_id' => $r->user()->organization_id, 'disk' => $disk, 'path' => $filePath, 'original_name' => $upload->name,
                    'mime' => $upload->mime, 'size' => $upload->size, 'visibility' => $d['visibility'], 'alt' => $d['alt'] ?? null, 'width' => $upload->width, 'height' => $upload->height]);
                if ($persistent || $private) {
                    $bytes = file_get_contents($upload->path);
                    abort_if($bytes === false, 503, 'خواندن فایل آپلودشده ممکن نیست.');
                    if ($persistent) PublicImages::save($media, $bytes);
                    if ($private) PrivateFiles::save($media, $bytes);
                }
                return $media;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($filePath);
            throw $e;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $upload->cleanup();
        }
        Audit::record('media.created', $media);

        return Api::ok($media, 'فایل ذخیره شد.', 201);
    }

    public function download(Request $r, int $id)
    {
        $m = Media::findOrFail($id);
        abort_unless(FileAccess::mayRead($m, $r->user()), 404);
        if (PrivateFiles::supports($m)) {
            $bytes = PrivateFiles::read($m);
            abort_if($bytes === null, 404);
            return response()->streamDownload(fn()=>print($bytes), $m->original_name,
                ['Content-Type'=>$m->mime, 'X-Content-Type-Options'=>'nosniff', 'Cache-Control'=>'private, no-store']);
        }
        if ($m->disk === 'page-assets') return response()->streamDownload(fn()=>print(\App\Services\PageImages::bytes($m)), $m->original_name, ['Content-Type'=>$m->mime,'X-Content-Type-Options'=>'nosniff']);
        if (PublicImages::supports($m)) {
            $bytes = PublicImages::read($m);
            abort_if($bytes === null, 404);
            return response()->streamDownload(fn()=>print($bytes), $m->original_name,
                ['Content-Type'=>$m->mime, 'X-Content-Type-Options'=>'nosniff', 'Cache-Control'=>'private, no-store']);
        }
        abort_unless(Storage::disk($m->disk)->exists($m->path), 404);

        return Storage::disk($m->disk)->download($m->path, $m->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $r, int $id)
    {
        $m = Media::findOrFail($id);
        abort_unless(FileAccess::mayManage($m, $r->user()), 404);
        $used = DB::table('mediables')->where('media_id', $id)->exists();
        foreach (['banner_media_id', 'aerial_media_id'] as $field) {
            $used = $used || DB::table('organizations')->where($field, $id)->exists();
        }
        foreach (['contents' => 'cover_media_id', 'slides' => 'media_id', 'members' => 'media_id', 'links' => 'media_id', 'gallery_items' => 'media_id'] as $table => $fk) {
            $used = $used || DB::table($table)->where($fk, $id)->exists();
        }
        $slogan = Setting::where('key', 'site.slogan')->value('value');
        $used = $used || (is_array($slogan) && (int) ($slogan['id'] ?? 0) === $id) ||
            ($m->url && (is_array($slogan) ? ($slogan['url'] ?? null) : $slogan) === $m->url);
        abort_if($used, 409, 'فایل هنوز در محتوا یا مکاتبات استفاده می‌شود.');
        // A tombstone prevents broken references; the file is physically removed only when unreferenced.
        if ($m->disk === 'page-assets') DB::table('custom_page_images')->where('media_id',$m->id)->delete();
        elseif ($m->disk !== 'portal-icons') Storage::disk($m->disk)->delete($m->path);
        $m->delete();
        // Retain the durable original alongside the soft-deleted media record.
        Audit::record('media.deleted',$m);

        return response()->noContent();
    }
}
