<?php

namespace App\Models;

final class CustomPage extends PortalModel
{
    use HasMedia;

    protected function casts(): array { return parent::casts() + ['placements' => 'array']; }
    public function linkImage() { return $this->belongsTo(Media::class, 'link_image_media_id'); }
    public function scopeVisible($query) { return $query->where('status', 'published'); }
}
