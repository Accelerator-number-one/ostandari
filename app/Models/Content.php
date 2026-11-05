<?php

namespace App\Models;

class Content extends PortalModel
{
    use HasMedia;

    protected $appends = ['news_scope', 'portal_path', 'duty_group'];

    public function getDutyGroupAttribute(): ?string
    {
        $group = $this->metadata['duty_group'] ?? null;
        return is_string($group) ? $group : null;
    }

    public function getPortalPathAttribute(): ?string
    {
        return $this->metadata['portal_path'] ?? null;
    }

    public function getNewsScopeAttribute(): ?string
    {
        return $this->metadata['news_scope'] ?? null;
    }

    protected function casts(): array
    {
        return parent::casts() + ['show_in_slideshow' => 'boolean'];
    }

    public function cover()
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id')->select(['id', 'name']);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'content_tag');
    }

    public function scopeVisible($q)
    {
        return $q->published()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q)=>$q->where('type','!=','page')->orWhere('slug','!=','official-page-menu-5'));
    }
}
