<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

class Media extends PortalModel
{
    protected $table = 'media';

    protected $hidden = ['disk', 'path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        if ($this->visibility !== 'public') return null;
        if ($this->disk === 'portal-icons') return '/design/icons/'.basename($this->path);
        if ($this->disk === 'page-assets') return '/page-assets/'.$this->id.'/'.basename($this->path);
        if (\App\Services\PublicImages::supports($this)) return '/media-assets/'.$this->id.'/'.basename($this->path);
        return Storage::disk('public')->url($this->path);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
