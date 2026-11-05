<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = ['is_active' => true, 'person_type' => 'citizen', 'access_scope' => 'organization'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'email_verified_at' => 'datetime', 'mobile_verified_at' => 'datetime', 'last_login_at' => 'datetime'];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function staffPortal()
    {
        return $this->hasOne(StaffPortal::class, 'owner_user_id');
    }

    public function isStaffManager(): bool
    {
        return $this->role?->name === \App\Services\StaffPortals::ROLE;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $permissions = $this->effectivePermissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_active && $this->role?->name === 'super_admin';
    }

    public function isPublicRelations(): bool
    {
        return in_array($this->role?->name, ['editor', 'province_public_relations', 'county_public_relations'], true);
    }

    public function hasGlobalAccess(): bool
    {
        return $this->access_scope === 'all' && ! $this->isPublicRelations() && ! $this->isStaffManager();
    }

    public function hasReadOnlyWorkspace(): bool
    {
        return $this->isPublicRelations() && $this->organization?->type === 'governorate';
    }

    public function effectivePermissions(): array
    {
        $permissions = $this->role?->permissions ?? [];
        if ($this->isStaffManager()) return ['staff-portal.manage'];
        if (! $this->isPublicRelations()) return $permissions;
        $allowed = \App\Services\Workspace::PUBLIC_RELATIONS_PERMISSIONS;
        return in_array('*', $permissions, true) ? $allowed : array_values(array_intersect($permissions, $allowed));
    }

    public function isStaff(): bool
    {
        return $this->person_type !== 'citizen' && count($this->role?->permissions ?? []) > 0;
    }
}
