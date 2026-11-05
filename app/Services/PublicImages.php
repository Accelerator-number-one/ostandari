<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\{DB, Schema, Storage};

/** Keep public uploads available when an application container is replaced or scaled. */
final class PublicImages
{
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function supports(Media $media): bool
    {
        return $media->disk === 'public' && $media->visibility === 'public' && in_array($media->mime, self::MIMES, true);
    }

    public static function ready(): bool { return Schema::hasTable('public_media_files'); }

    public static function assertReady(): void
    {
        abort_unless(self::ready(), 503, 'ذخیره‌سازی پایدار تصاویر آماده نیست. ابتدا php artisan portal:prepare-public-images را اجرا کنید.');
    }

    public static function save(Media $media, string $bytes): void
    {
        abort_unless(self::supports($media), 422);
        $image = @getimagesizefromstring($bytes);
        abort_unless($image && ($image['mime'] ?? null) === $media->mime, 422, 'فایل تصویر معتبر نیست.');
        DB::table('public_media_files')->insertOrIgnore(['media_id'=>$media->id, 'base64'=>base64_encode($bytes), 'sha256'=>hash('sha256', $bytes)]);
    }

    public static function stored(Media $media): ?string
    {
        if (!self::supports($media) || !self::ready()) return null;
        $row = DB::table('public_media_files')->where('media_id', $media->id)->first();
        if (!$row) return null;
        $bytes = base64_decode($row->base64, true);
        return $bytes !== false && hash_equals($row->sha256, hash('sha256', $bytes)) ? $bytes : null;
    }

    public static function read(Media $media): ?string
    {
        if (!self::supports($media)) return null;
        if (($bytes = self::stored($media)) !== null) return $bytes;
        $disk = Storage::disk('public');
        if (!$disk->exists($media->path)) return null;
        $bytes = $disk->get($media->path);
        if (self::ready()) self::save($media, $bytes);
        return $bytes;
    }
}
