<?php

namespace App\Models;

trait HasMedia
{
    public function media()
    {
        return $this->morphToMany(Media::class, 'mediable')->withPivot('purpose')->withTimestamps();
    }
}
