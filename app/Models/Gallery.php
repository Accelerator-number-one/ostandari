<?php

namespace App\Models;

class Gallery extends PortalModel
{
    public function items()
    {
        return $this->hasMany(GalleryItem::class)->orderBy('priority')->with('image');
    }
}
