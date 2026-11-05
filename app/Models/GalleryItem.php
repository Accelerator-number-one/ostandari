<?php

namespace App\Models;

class GalleryItem extends PortalModel
{
    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }
}
