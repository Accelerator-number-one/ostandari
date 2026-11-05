<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class StaffPortal extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function member() { return $this->belongsTo(Member::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function items() { return $this->hasMany(StaffPortalItem::class); }
    public function pages() { return $this->hasMany(StaffPortalPage::class); }
    public function assets() { return $this->hasMany(StaffPortalAsset::class); }
    public function portrait() { return $this->belongsTo(StaffPortalAsset::class, 'portrait_id'); }
    public function banner() { return $this->belongsTo(StaffPortalAsset::class, 'banner_id'); }
    public function isGovernor(): bool { return $this->member?->kind === 'county_governor'; }
    public function publicPath(): string { return '/staff/'.$this->slug; }
    public function adminPath(): string { return '/staff-admin/'.$this->slug; }
}
