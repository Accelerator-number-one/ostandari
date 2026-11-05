<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdministratorBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedAdministratorFixture = false;

    public function test_system_seeding_does_not_create_environment_based_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 7);
        $this->assertDatabaseCount('counties', 22);
    }

    public function test_explicit_creation_saves_a_hash_and_login_uses_the_database_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('portal:admin-create', ['--username' => 'database-admin', '--email' => 'database-admin@example.test'])
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'Database-Password42!')
            ->expectsQuestion('Confirm password', 'Database-Password42!')
            ->doesntExpectOutputToContain('Database-Password42!')
            ->assertSuccessful();

        $admin = User::where('username', 'database-admin')->firstOrFail();
        $hash = $admin->password;
        $this->assertNotSame('Database-Password42!', $hash);
        $this->assertTrue(Hash::check('Database-Password42!', $hash));
        $this->assertTrue($admin->isSuperAdmin());
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($hash, $admin->fresh()->password);
        $this->postJson('/session/login', ['login' => 'database-admin', 'password' => 'Testing-Only-Password42!', 'audience' => 'staff'])->assertUnprocessable();
        $this->postJson('/session/login', ['login' => 'database-admin', 'password' => 'Database-Password42!', 'audience' => 'staff'])->assertOk();
    }

    public function test_creation_never_resets_an_existing_database_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::create([
            'username' => 'admin', 'name' => 'Imported administrator', 'password' => 'Imported-Password42!',
            'role_id' => Role::where('name', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'mazandaran')->value('id'),
            'person_type' => 'staff', 'access_scope' => 'all',
        ]);
        $original = $admin->fresh()->getAttributes();
        $this->artisan('portal:admin-create')->assertFailed();
        $this->assertSame($original, $admin->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }
}
