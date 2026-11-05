<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class PortalModel extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_current' => 'boolean', 'is_featured' => 'boolean', 'comments_enabled' => 'boolean',
            'priority' => 'integer', 'published_at' => 'datetime', 'expires_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'opens_at' => 'datetime', 'closes_at' => 'datetime', 'term_start' => 'datetime', 'term_end' => 'datetime',
            'metadata' => 'array', 'fields' => 'array', 'steps' => 'array', 'requirements' => 'array'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeInWindow($query)
    {
        return $query->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function image()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
