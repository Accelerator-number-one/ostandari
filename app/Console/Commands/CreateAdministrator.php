<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class CreateAdministrator extends Command
{
    protected $signature = 'portal:admin-create {--username=admin} {--name=} {--email=}';
    protected $description = 'Create a database administrator using a hidden password prompt without changing existing accounts';

    public function handle(): int
    {
        $role = Role::where('name', 'super_admin')->first();
        $organization = Organization::where('slug', 'mazandaran')->first();
        if (! $role || ! $organization) {
            $this->error('Run php artisan migrate --seed before creating an administrator.');
            return self::FAILURE;
        }
        $username = $this->option('username');
        if (User::where('username', $username)->exists()) {
            $this->error('This username already exists. Its password and account were not changed.');
            return self::FAILURE;
        }
        $data = [
            'username' => $username, 'name' => $this->option('name') ?: 'مدیر سامانه', 'email' => $this->option('email'),
            'password' => $this->secret('Password (12+ characters, mixed case and numbers)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'username' => 'required|string|max:80|alpha_dash:ascii|unique:users,username',
            'name' => 'required|string|max:120', 'email' => 'nullable|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }
        $attributes = $validator->validated();
        unset($attributes['password_confirmation']);
        User::create($attributes + [
            'role_id' => $role->id, 'organization_id' => $organization->id,
            'person_type' => 'staff', 'access_scope' => 'all', 'is_active' => true,
        ]);
        $this->info('Administrator saved to the database. The password was not printed or written to .env.');
        $this->line('/panel/admin/login');
        return self::SUCCESS;
    }
}
