<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('slides', 'organization_id')) {
            throw new \RuntimeException('Database schema is outdated. Run php artisan migrate --seed to apply pending migrations before seeding. Existing data will be preserved.');
        }

        DB::transaction(function () {
            $roles = [
                'super_admin' => ['مدیر اصلی', ['*']],
                'administrator' => ['مدیر سامانه', config('portal.permissions')],
                'editor' => ['روابط عمومی', ['dashboard.view', 'content.manage', 'categories.manage', 'navigation.manage', 'media.manage', 'members.manage', 'comments.manage']],
                'operator' => ['کارشناس پاسخ‌گویی', ['dashboard.view', 'tickets.manage', 'forms.manage']],
                'citizen' => ['شهروند', []],
            ];
            foreach ($roles as $key => [$label,$permissions]) {
                Role::firstOrCreate(['name' => $key], ['label' => $label, 'permissions' => $permissions, 'is_system' => true]);
            }
            $root = Organization::firstOrCreate(['slug' => 'mazandaran'], ['name' => 'استانداری مازندران', 'type' => 'governorate', 'is_active' => true]);
        });
        $this->call(CountySeeder::class);
        $this->call(PortalRolesSeeder::class);
        $this->command?->info('Base roles and organizations ready. Existing accounts and passwords were preserved. Import your users, or run php artisan portal:admin-create for a new administrator.');
    }
}
