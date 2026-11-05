<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyDatabaseUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => 'database/migrations/2026_10_17_000001_create_portal_schema.php'];
    }

    public function test_seeding_an_old_schema_reports_the_upgrade_command_before_writing_data(): void
    {
        $this->assertFalse(Schema::hasColumn('slides', 'organization_id'));

        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('Seeding must require migrations on a legacy database.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('php artisan migrate --seed', $exception->getMessage());
        }

        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_migrate_and_seed_upgrades_a_legacy_database_without_losing_its_admin_or_content(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Legacy administrator', 'permissions' => ['*'], 'is_system' => true]);
        $root = Organization::create(['slug' => 'mazandaran', 'name' => 'استانداری مازندران', 'type' => 'governorate', 'is_active' => true]);
        $admin = User::create([
            'name' => 'Legacy administrator', 'username' => 'admin', 'email' => 'legacy-admin@example.test',
            'password' => 'Legacy-Admin-Password42!', 'role_id' => $role->id, 'organization_id' => $root->id,
            'person_type' => 'staff', 'access_scope' => 'all', 'is_active' => true,
        ]);
        $originalAdmin = $admin->fresh()->getAttributes();
        $slideId = DB::table('slides')->insertGetId(['title' => 'Legacy slide', 'url' => '/news']);
        $contentId = DB::table('contents')->insertGetId([
            'organization_id' => $root->id, 'author_id' => $admin->id, 'type' => 'news',
            'title' => 'خبر قدیمی', 'slug' => 'legacy-news', 'body' => '<p>متن خبر قبلی</p>',
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $this->artisan('migrate', ['--seed' => true, '--force' => true])->assertSuccessful();

        foreach (['menus', 'slides', 'links', 'faqs', 'quotes', 'polls', 'tags', 'media'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'organization_id'), $table);
        }
        $this->assertTrue(Schema::hasColumn('contents', 'show_in_slideshow'));
        $this->assertTrue(Schema::hasColumn('organizations', 'banner_media_id'));
        $this->assertTrue(Schema::hasColumn('otp_challenges', 'registration_terms'));
        $this->assertDatabaseHas('slides', ['id' => $slideId, 'organization_id' => $root->id, 'title' => 'Legacy slide']);
        $this->assertDatabaseHas('contents', ['id' => $contentId, 'title' => 'خبر قدیمی', 'body' => '<p>متن خبر قبلی</p>']);
        $this->assertSame($originalAdmin, $admin->fresh()->getAttributes());
        $this->assertTrue(Hash::check('Legacy-Admin-Password42!', $admin->fresh()->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('counties', 22);
        $this->get('/')->assertOk();
        $this->get('/amol')->assertOk();
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.slides.0.id', $slideId);

        $this->artisan('migrate', ['--seed' => true, '--force' => true])->assertSuccessful();

        $this->assertSame($originalAdmin, $admin->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('contents', 1);
        $this->assertDatabaseCount('slides', 1);
    }
}
