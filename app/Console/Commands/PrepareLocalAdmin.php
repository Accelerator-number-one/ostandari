<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PrepareLocalAdmin extends Command
{
    protected $signature = 'portal:local-admin';
    protected $description = 'Create or reset the requested local admin / admin account';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('The local account is only available in local or testing environments.');

            return self::FAILURE;
        }
        DB::transaction(function () {
            $role = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'مدیر اصلی', 'permissions' => ['*'], 'is_system' => true]);
            $root = Organization::firstOrCreate(['slug' => 'mazandaran'], ['name' => 'استانداری مازندران', 'type' => 'governorate', 'is_active' => true]);
            $user = User::firstOrNew(['username' => 'admin']);
            $user->fill(['name' => 'مدیر سامانه', 'email' => $user->email ?: 'admin@ostandari.test', 'password' => 'admin',
                'role_id' => $role->id, 'organization_id' => $root->id, 'person_type' => 'staff', 'access_scope' => 'all', 'is_active' => true]);
            $user->save();
            $user->tokens()->delete();
        });
        $this->info('Local administrator ready. Username: admin  Password: admin');

        return self::SUCCESS;
    }
}
