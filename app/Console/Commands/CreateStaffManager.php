<?php

namespace App\Console\Commands;

use App\Models\{Role, StaffPortal, User};
use App\Services\StaffPortals;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\Rules\Password;

final class CreateStaffManager extends Command
{
    protected $signature = 'portal:staff-manager {slug : Personal page slug, e.g. it or governor-123} {--username= : New unique username}';
    protected $description = 'Create an owner account for one staff or governor personal page; enter its password at the hidden prompt.';
    public function handle(): int
    {
        if (!StaffPortals::ready()) { $this->error('Run portal:prepare-staff-portals first.'); return self::FAILURE; }
        $portal = StaffPortals::visible()->where('slug', $this->argument('slug'))->first();
        if (!$portal) { $this->error('Staff page not found.'); return self::FAILURE; }
        if ($portal->owner_user_id) { $this->error('This page already has an account. No password was changed.'); return self::FAILURE; }
        $data = ['username' => $this->option('username') ?: $this->ask('Username'), 'password' => $this->secret('Password (12+ characters, mixed case and numbers)')];
        $validator = Validator::make($data, ['username' => 'required|alpha_dash:ascii|max:80|unique:users,username',
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()]]);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return self::FAILURE; }
        DB::transaction(function () use ($portal, $data) {
            $locked = StaffPortal::lockForUpdate()->findOrFail($portal->id);
            abort_if($locked->owner_user_id, 409);
            $user = User::create($data + ['name' => $portal->member->name, 'organization_id' => $portal->member->organization_id,
                'role_id' => Role::where('name', StaffPortals::ROLE)->value('id'), 'person_type' => 'staff', 'access_scope' => 'organization', 'is_active' => true]);
            $locked->update(['owner_user_id' => $user->id]);
        });
        $this->info('Account created. Password was not printed.');
        $this->line($portal->adminPath().'/login');
        return self::SUCCESS;
    }
}
