<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InlineMedia
{
    /** Track uploaded images in rich text so a used file cannot be deleted. */
    public static function sync(Model $model, User $actor): void
    {
        $attributes = $model->getAttributes();
        $html = implode('\n', array_intersect_key($attributes, array_flip(['body', 'description', 'bio', 'answer'])));
        self::syncHtml($model, $actor, $html);
    }

    public static function syncHtml(Model $model, User $actor, string $html, int $limit = 20): void
    {
        $paths = [];
        $pageImages = [];
        $publicImages = [];
        $base = rtrim(Storage::disk('public')->url(''), '/');
        $prefix = rtrim(parse_url($base, PHP_URL_PATH) ?: '/storage', '/').'/';
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($document->getElementsByTagName('img') as $image) {
                $url = $image->getAttribute('src');
                $host = parse_url($url, PHP_URL_HOST);
                if ((!$host || in_array($host,[parse_url(config('app.url'),PHP_URL_HOST),request()->getHost()],true)) &&
                    preg_match('~^/page-assets/([0-9]+)/([a-zA-Z0-9-]+\.(?:jpg|jpeg|png|webp))$~', parse_url($url,PHP_URL_PATH)??'', $asset)) $pageImages[(int)$asset[1]] = $asset[2];
                if ((!$host || in_array($host,[parse_url(config('app.url'),PHP_URL_HOST),request()->getHost()],true)) &&
                    preg_match('~^/media-assets/([0-9]+)/([a-zA-Z0-9-]+\.(?:jpg|jpeg|png|webp))$~', parse_url($url,PHP_URL_PATH)??'', $asset)) $publicImages[(int)$asset[1]] = $asset[2];
                if (str_starts_with($url, $base.'/') || str_starts_with($url, $prefix)) {
                    $path = parse_url($url, PHP_URL_PATH) ?: '';
                    if (str_starts_with($path, $prefix)) {
                        $paths[] = rawurldecode(substr($path, strlen($prefix)));
                    }
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $paths = array_values(array_unique($paths));
        if (count($paths) + count($pageImages) + count($publicImages) > $limit) {
            throw ValidationException::withMessages(['body' => 'تعداد تصاویر متن بیش از حد مجاز است.']);
        }
        $files = Media::whereIn('path', $paths)->where('disk', 'public')->where('visibility', 'public')->lockForUpdate()->get();
        if ($files->count() !== count($paths)) {
            throw ValidationException::withMessages(['body' => 'یکی از تصاویر متن حذف شده یا عمومی نیست.']);
        }
        if ($pageImages) {
            $assets = Media::whereIn('id',array_keys($pageImages))->where('disk','page-assets')->where('visibility','public')->lockForUpdate()->get();
            if ($assets->count()!==count($pageImages) || $assets->contains(fn($file)=>$file->path!==$pageImages[$file->id]))
                throw ValidationException::withMessages(['body'=>'یکی از تصاویر متن حذف شده یا معتبر نیست.']);
            $files=$files->concat($assets);
        }
        if ($publicImages) {
            $assets = Media::whereIn('id',array_keys($publicImages))->where('disk','public')->where('visibility','public')->lockForUpdate()->get();
            if ($assets->count()!==count($publicImages) || $assets->contains(fn($file)=>basename($file->path)!==$publicImages[$file->id]))
                throw ValidationException::withMessages(['body'=>'یکی از تصاویر متن حذف شده یا معتبر نیست.']);
            $files=$files->concat($assets);
        }
        foreach ($files as $file) {
            abort_unless(str_starts_with($file->mime, 'image/'), 422, 'فایل درج‌شده در متن باید تصویر باشد.');
            $alreadyAttached = DB::table('mediables')->where('mediable_type', $model->getMorphClass())->where('mediable_id', $model->id)->where('media_id', $file->id)->exists();
            abort_unless($alreadyAttached || FileAccess::mayManage($file, $actor), 403, 'تصویر متعلق به این حساب نیست.');
        }
        $scope = fn () => DB::table('mediables')->where('mediable_type', $model->getMorphClass())->where('mediable_id', $model->id);
        $scope()->where('purpose', 'inline')->whereNotIn('media_id', $files->pluck('id'))->delete();
        foreach ($files as $file) {
            DB::table('mediables')->insertOrIgnore(['media_id' => $file->id, 'mediable_type' => $model->getMorphClass(), 'mediable_id' => $model->id, 'purpose' => 'inline', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
