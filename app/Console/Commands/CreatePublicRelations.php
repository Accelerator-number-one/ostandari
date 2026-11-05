<?php

namespace App\Console\Commands;

use App\Models\County;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\CountySlugs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class CreatePublicRelations extends Command
{
    protected $signature = 'portal:public-relations {county? : Canonical county slug} {--province : Create a read-only provincial PR account} {--username= : Unique username} {--name= : Display name} {--email= : Optional email}';
    protected $description = 'Create an organization-bound public relations account using a hidden password prompt';

    public function handle(): int
    {
        if ((bool) $this->argument('county') === (bool) $this->option('province')) {
            $this->error('Choose a county slug, or --province, but not both.');
            return self::FAILURE;
        }
        $county = $this->option('province') ? null : County::active()->whereIn('slug', CountySlugs::variants((string) $this->argument('county')))->first();
        $organization = $county ? Organization::active()->where('type', 'county')->where('county_id', $county->id)->first() : ($this->option('province') ? Organization::active()->where('type', 'governorate')->first() : null);
        $role = Role::where('name', $county ? 'county_public_relations' : 'province_public_relations')->first();
        if (! $organization || ! $role) {
            $this->error('Organization or portal role is missing. Run CountySeeder and PortalRolesSeeder first.');
            return self::FAILURE;
        }
        $data = ['username' => $this->option('username') ?: $this->ask('Username'), 'name' => $this->option('name') ?: 'روابط عمومی '.$organization->name, 'email' => $this->option('email'), 'password' => $this->secret('Password (at least 12 characters, mixed case and numbers)')];
        $validator = Validator::make($data, ['username' => 'required|string|max:80|alpha_dash:ascii|unique:users,username', 'name' => 'required|string|max:120', 'email' => 'nullable|email|max:255|unique:users,email', 'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }
        User::create($validator->validated() + ['role_id' => $role->id, 'organization_id' => $organization->id, 'person_type' => 'staff', 'access_scope' => 'organization', 'is_active' => true]);
        $this->info('Account created for '.$organization->name.'. Password was not printed.');
        $this->line($county ? '/county-admin/'.CountySlugs::variants($county->slug)[0].'/login' : '/panel/admin/login');
        return self::SUCCESS;
    }
}
