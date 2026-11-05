<?php

namespace App\Models;

class Link extends PortalModel
{
    protected $appends = ['icon'];

    public function getIconAttribute(): ?string
    {
        $image = $this->image;
        $key = $image?->disk === 'portal-icons' ? pathinfo($image->path, PATHINFO_FILENAME) : null;
        return isset(\App\Services\PortalIcons::ITEMS[$key ?? '']) ? $key : null;
    }
}
