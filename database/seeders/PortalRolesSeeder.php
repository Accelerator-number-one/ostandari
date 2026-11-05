<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PortalRolesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $root = \App\Models\Organization::where('type', 'governorate')->value('id');
            if ($root) {
                foreach (['menus', 'slides', 'links', 'faqs', 'quotes', 'polls', 'tags'] as $table) {
                    DB::table($table)->whereNull('organization_id')->update(['organization_id' => $root]);
                }
            }
            $county = Role::updateOrCreate(['name' => 'county_public_relations'], ['label' => 'روابط عمومی فرمانداری', 'permissions' => Workspace::PUBLIC_RELATIONS_PERMISSIONS, 'is_system' => true]);
            $province = Role::updateOrCreate(['name' => 'province_public_relations'], ['label' => 'روابط عمومی استانداری — فقط مشاهده', 'permissions' => Workspace::PUBLIC_RELATIONS_PERMISSIONS, 'is_system' => true]);
            $editor = Role::where('name', 'editor')->value('id');
            User::whereIn('role_id', array_filter([$editor, $county->id, $province->id]))->with('organization')->chunkById(200, function ($users) use ($county, $province) {
                foreach ($users as $user) {
                    $role = match ($user->organization?->type) { 'county' => $county, 'governorate' => $province, default => null };
                    if ($role) $user->update(['role_id' => $role->id, 'access_scope' => 'organization']);
                }
            });
        });
    }
}
