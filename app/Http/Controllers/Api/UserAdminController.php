<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use App\Models\User;
use App\Rules\NationalCode;
use App\Services\Access;
use App\Services\Audit;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAdminController
{
    private function query(Request $r)
    {
        return Access::scope(User::query()->with('role', 'organization'), $r->user());
    }

    public function index(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'q' => 'sometimes|string|max:100', 'person_type' => 'sometimes|in:citizen,staff,employee,expert', 'is_active' => 'sometimes|boolean']);
        $q = $this->query($r);
        foreach (['person_type', 'is_active'] as $key) {
            if ($r->has($key)) {
                $q->where($key, $r->input($key));
            }
        }
        if ($r->filled('q')) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->input('q').'%')->orWhere('username', 'like', '%'.$r->input('q').'%')->orWhere('email', 'like', '%'.$r->input('q').'%')->orWhere('mobile', 'like', '%'.$r->input('q').'%'));
        }

        return Api::page($q->latest('id')->paginate((int) $r->input('per_page', 20)));
    }

    public function show(Request $r, int $id)
    {
        return Api::ok($this->query($r)->findOrFail($id));
    }

    private function rules(?User $u = null): array
    {
        return ['name' => [$u ? 'sometimes' : 'required', 'string', 'max:120'], 'username' => ['sometimes', 'nullable', 'alpha_dash:ascii', 'max:80', Rule::unique('users')->ignore($u?->id)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users')->ignore($u?->id)],
            'mobile' => ['sometimes', 'nullable', 'regex:/^09[0-9]{9}$/', Rule::unique('users')->ignore($u?->id)],
            'national_code' => ['sometimes', 'nullable', 'string', new NationalCode, Rule::unique('users')->ignore($u?->id)],
            'personnel_code' => 'sometimes|nullable|string|max:60', 'password' => [$u ? 'sometimes' : 'required', 'string', Password::min(12)->letters()->mixedCase()->numbers()],
            'role_id' => [$u ? 'sometimes' : 'required', Rule::exists('roles', 'id')], 'organization_id' => ['sometimes', 'nullable', Rule::exists('organizations', 'id')->whereNull('deleted_at')],
            'person_type' => 'sometimes|in:citizen,staff,employee,expert', 'access_scope' => 'sometimes|in:all,organization', 'is_active' => 'sometimes|boolean'];
    }

    private function protect(Request $r, array $data, ?User $target = null): void
    {
        $actor = $r->user();
        $role = Role::findOrFail($data['role_id'] ?? $target?->role_id);
        if (in_array($role->name, ['county_public_relations', 'province_public_relations'], true)) {
            $organization = \App\Models\Organization::find($data['organization_id'] ?? $target?->organization_id);
            abort_unless($organization?->is_active && $organization->type === ($role->name === 'county_public_relations' ? 'county' : 'governorate'), 422, 'نقش روابط عمومی باید با استان یا فرمانداری انتخاب‌شده مطابقت داشته باشد.');
            abort_if(($data['access_scope'] ?? $target?->access_scope ?? 'organization') !== 'organization', 422, 'دسترسی روابط عمومی باید به استان یا فرمانداری مربوط محدود باشد.');
        }
        if (! $actor->isSuperAdmin()) {
            abort_if($target?->isSuperAdmin() || $role->name === 'super_admin', 403, 'مدیریت مدیر اصلی مجاز نیست.');
            if (! $target || isset($data['role_id']) && $data['role_id'] !== $target->role_id) {
                abort_unless($actor->hasPermission('roles.manage'), 403);
            }
            foreach ($role->permissions as $permission) {
                abort_unless($actor->hasPermission($permission), 403, 'اعطای دسترسی بالاتر از سطح خودتان مجاز نیست.');
            }
            if (($data['access_scope'] ?? $target?->access_scope ?? 'organization') === 'all' && ! $actor->hasGlobalAccess()) {
                abort(403);
            }
        }
        if (! $actor->hasGlobalAccess()) {
            Access::organization($actor, $data['organization_id'] ?? $target?->organization_id ?? $actor->organization_id);
        }
        if ($target && $target->id === $actor->id) {
            abort_if(array_key_exists('is_active', $data) && ! $data['is_active'] || isset($data['role_id']) && $data['role_id'] !== $actor->role_id || isset($data['access_scope']) && $data['access_scope'] !== $actor->access_scope, 409, 'دسترسی یا وضعیت حساب فعلی خود را از این مسیر تغییر ندهید.');
        }
        if ($target?->isSuperAdmin() && (array_key_exists('is_active', $data) && ! $data['is_active'] || $role->name !== 'super_admin')) {
            abort_if(User::where('role_id', $target->role_id)->where('is_active', true)->count() <= 1, 409, 'آخرین مدیر اصلی فعال قابل حذف یا غیرفعال‌سازی نیست.');
        }
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->rules());
        $d['person_type'] ??= 'staff';
        $d['organization_id'] ??= $r->user()->organization_id;
        $r->validate(['username' => 'required_without_all:email,mobile']);
        $this->protect($r, $d);
        $u = User::create($d);
        Audit::record('user.created', $u, $d);

        return Api::ok($u->load('role', 'organization'), 'کاربر ایجاد شد.', 201);
    }

    public function update(Request $r, int $id)
    {
        $u = $this->query($r)->findOrFail($id);
        $d = $r->validate($this->rules($u));
        DB::transaction(function () use ($r, $u, $d) {
            $locked = User::lockForUpdate()->findOrFail($u->id);
            $this->protect($r, $d, $locked);
            $locked->update($d);
            $locked->tokens()->delete();
            Audit::record('user.updated', $locked, $d);
        });

        return Api::ok($u->refresh()->load('role', 'organization'));
    }

    public function destroy(Request $r, int $id)
    {
        $u = $this->query($r)->findOrFail($id);
        $this->protect($r, ['is_active' => false], $u);
        $u->update(['is_active' => false]);
        $u->tokens()->delete();
        Audit::record('user.deactivated', $u);

        return response()->noContent();
    }

    public function roles(Request $r)
    {
        return Api::ok(['permissions' => config('portal.permissions'), 'roles' => Role::orderBy('id')->get()]);
    }

    public function showRole(int $id)
    {
        return Api::ok(Role::findOrFail($id));
    }

    private function roleData(Request $r, ?Role $role = null): array
    {
        abort_unless($r->user()->hasGlobalAccess(), 403);
        abort_unless($r->user()->hasGlobalAccess(), 403, 'مدیریت نقش‌های سراسری به دسترسی مرکزی نیاز دارد.');
        $d = $r->validate(['name' => [$role ? 'sometimes' : 'required', 'alpha_dash:ascii', 'max:60', Rule::unique('roles')->ignore($role?->id)], 'label' => [$role ? 'sometimes' : 'required', 'string', 'max:120'],
            'permissions' => [$role ? 'sometimes' : 'required', 'array', 'max:30'], 'permissions.*' => ['string', 'distinct', Rule::in(config('portal.permissions'))]]);
        abort_if($role?->is_system, 409, 'نقش‌های سیستمی قابل تغییر نیستند؛ نقش جدید بسازید.');
        foreach ($d['permissions'] ?? [] as $p) {
            abort_unless($r->user()->hasPermission($p), 403, 'امکان اعطای این مجوز را ندارید.');
        }
        if ($role) {
            foreach ($role->permissions as $p) {
                abort_unless($r->user()->hasPermission($p), 403);
            }
        }

        return $d;
    }

    public function createRole(Request $r)
    {
        $role = Role::create($this->roleData($r));
        Audit::record('role.created', $role);

        return Api::ok($role, 'نقش ایجاد شد.', 201);
    }

    public function updateRole(Request $r, int $id)
    {
        $role = Role::findOrFail($id);
        $role->update($this->roleData($r, $role));
        Audit::record('role.updated', $role);

        return Api::ok($role);
    }

    public function deleteRole(Request $r, int $id)
    {
        $role = Role::findOrFail($id);
        $this->roleData($r,$role);
        abort_if(User::where('role_id',$id)->exists(),409,'این نقش کاربر دارد.');
        $role->delete();
        Audit::record('role.deleted');

        return response()->noContent();
    }
}
