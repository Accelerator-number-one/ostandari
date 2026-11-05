<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

final class PortalTestSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        parent::run();
        User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Test administrator', 'email' => 'admin@ostandari.test', 'password' => 'Testing-Only-Password42!',
            'role_id' => Role::where('name', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'mazandaran')->value('id'),
            'person_type' => 'staff', 'access_scope' => 'all', 'is_active' => true,
        ]);
    }
}
