<?php

namespace App\Http\Controllers\Web;

use App\Models\Media;
use App\Services\PublicImages;
use Illuminate\Http\Request;

final class PublicImageController
{
    private function query()
    {
        return Media::where('disk', 'public')->where('visibility', 'public')->whereIn('mime', PublicImages::MIMES);
    }

    public function show(Request $request, int $id, string $name)
    {
        $media = $this->query()->find($id);
        abort_unless($media && hash_equals(basename($media->path), $name), 404, '', ['Cache-Control'=>'no-store']);
        return $this->image($request, $media);
    }

    /** Keep existing image URLs embedded in rich text and settings working too. */
    public function legacy(Request $request, string $path)
    {
        $media = $this->query()->where('path', $path)->first();
        abort_unless($media, 404, '', ['Cache-Control'=>'no-store']);
        return $this->image($request, $media);
    }

    private function image(Request $request, Media $media)
    {
        $bytes = PublicImages::read($media);
        abort_if($bytes === null, 404, '', ['Cache-Control'=>'no-store']);
        $response = response($bytes, 200, ['Content-Type'=>$media->mime, 'X-Content-Type-Options'=>'nosniff',
            'Cache-Control'=>'public, max-age=31536000, immutable']);
        $response->setEtag(hash('sha256', $bytes));
        $response->isNotModified($request);
        return $response;
    }
}
