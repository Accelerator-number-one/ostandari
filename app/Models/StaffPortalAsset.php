<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class StaffPortalAsset extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $hidden = ['base64'];
    protected $appends = ['url'];
    public function portal() { return $this->belongsTo(StaffPortal::class, 'staff_portal_id'); }
    public function getUrlAttribute(): string { return '/staff-assets/'.$this->id.'/'.$this->name; }
}
