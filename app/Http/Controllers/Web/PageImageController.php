<?php

namespace App\Http\Controllers\Web;

use App\Models\Media;
use App\Services\{CustomPages, PageImages};

final class PageImageController
{
    public function __invoke(int $id, string $name)
    {
        abort_unless(CustomPages::ready(), 404);
        $media = Media::where('disk','page-assets')->where('visibility','public')->where('path',$name)
            ->whereHas('organization',fn($q)=>$q->where('is_active',true))->findOrFail($id);
        abort_unless(in_array($media->mime,['image/jpeg','image/png','image/webp'],true), 404);
        return response(PageImages::bytes($media),200,['Content-Type'=>$media->mime,'X-Content-Type-Options'=>'nosniff',
            'Cache-Control'=>'public, max-age=31536000, immutable','ETag'=>'"'.$name.'"']);
    }
}
