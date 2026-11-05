<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Page-builder uploads survive replacement of a stateless application container. */
final class PageImages
{
    public static function store(User $actor, UploadedFile $file, MediaUploadPreparer $uploads, ?string $alt): Media
    {
        $org = CustomPages::organization($actor);
        $prepared = $uploads->prepare($file);
        try {
            $bytes = file_get_contents($prepared->path);
            abort_if($bytes === false || strlen($bytes) > 10 * 1024 * 1024, 422, 'حجم تصویر بیش از حد مجاز است.');
            return DB::transaction(function () use ($actor, $org, $prepared, $bytes, $alt) {
                $media = Media::create(['user_id'=>$actor->id,'organization_id'=>$org->id,'disk'=>'page-assets',
                    'path'=>Str::uuid().'.'.$prepared->extension,'original_name'=>$prepared->name,'mime'=>$prepared->mime,
                    'size'=>strlen($bytes),'visibility'=>'public','width'=>$prepared->width,'height'=>$prepared->height,'alt'=>$alt]);
                DB::table('custom_page_images')->insert(['media_id'=>$media->id,'base64'=>base64_encode($bytes)]);
                Audit::record('custom-pages.image-uploaded', $media);
                return $media;
            });
        } finally { $prepared->cleanup(); }
    }

    public static function bytes(Media $media): string
    {
        $encoded = DB::table('custom_page_images')->where('media_id',$media->id)->value('base64');
        abort_unless($encoded !== null, 404);
        $bytes = base64_decode($encoded, true);
        abort_if($bytes === false, 404);
        return $bytes;
    }
}
