<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\{DB, Schema, Storage};

/** Originals for authenticated downloads; never exposed by a public asset route. */
final class PrivateFiles
{
    public static function supports(Media $media): bool
    {
        return $media->disk === 'local' && $media->visibility === 'private';
    }

    public static function ready(): bool { return Schema::hasTable('private_media_files'); }

    public static function assertReady(): void
    {
        abort_unless(self::ready(),503,'ذخیره‌سازی پایدار پیوست‌ها آماده نیست. ابتدا php artisan portal:prepare-private-files را اجرا کنید.');
    }

    public static function save(Media $media, string $bytes): void
    {
        abort_unless(self::supports($media),422);
        DB::table('private_media_files')->insertOrIgnore(['media_id'=>$media->id,'base64'=>base64_encode($bytes),'sha256'=>hash('sha256',$bytes)]);
    }

    public static function stored(Media $media): ?string
    {
        if (!self::supports($media) || !self::ready()) return null;
        $row=DB::table('private_media_files')->where('media_id',$media->id)->first();
        if (!$row) return null;
        $bytes=base64_decode($row->base64,true);
        return $bytes!==false && hash_equals($row->sha256,hash('sha256',$bytes)) ? $bytes : null;
    }

    public static function read(Media $media): ?string
    {
        if (!self::supports($media)) return null;
        if (($bytes=self::stored($media))!==null) return $bytes;
        $disk=Storage::disk('local');
        if (!$disk->exists($media->path)) return null;
        $bytes=$disk->get($media->path);
        if (self::ready()) self::save($media,$bytes);
        return $bytes;
    }
}
