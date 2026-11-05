<?php

namespace Tests\Feature;

use App\Models\{Content, Organization, Role, Setting, User};
use App\Services\ServiceAgreementPages;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class ServiceAgreementPagesTest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    private function page(string $key, array $attributes = []): Content
    {
        return Content::create($attributes + ['organization_id' => $this->admin->organization_id,
            'author_id' => $this->admin->id, 'type' => 'page', 'locale' => 'fa',
            'slug' => ServiceAgreementPages::PAGES[$key]['slug'], 'title' => 'Original page',
            'body' => '<p>Original text</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    public function test_command_stores_both_complete_texts_in_the_database_and_renders_their_sections(): void
    {
        $this->artisan('portal:import-service-agreements')->assertExitCode(0);
        $this->assertSame(2, Content::where('type', 'page')->count());
        $tracking = ServiceAgreementPages::query('letter-tracking', $this->admin->organization_id)->firstOrFail();
        $network = ServiceAgreementPages::query('government-network-support', $this->admin->organization_id)->firstOrFail();
        $this->assertSame(6, substr_count($tracking->body, '<h2>'));
        $this->assertSame(8, substr_count($network->body, '<th scope="row">'));
        $this->assertSame(6, substr_count($network->body, '<li>'));
        $this->get('/section/menu-177')->assertOk()->assertSee('data-service-agreement="letter-tracking"', false)
            ->assertSee('هدف')->assertSee('مسئولیت')->assertSee('تعهدات متقابل')->assertSee('هزینه‌ها و پرداخت‌ها')
            ->assertSee('دوره عملکرد')->assertSee('خاتمه توافقنامه')->assertSee('پرتال استانداری مازندران')
            ->assertDontSee('لرستان')->assertDontSee('اطلاعات این بخش هنوز منتشر نشده است.')
            ->assertDontSee('مطالب مرتبط');
        $this->get('/section/menu-176')->assertOk()->assertSee('درخواست پشتیبانی شبکه دولت')
            ->assertSee('۱۰ روز کاری')->assertSee('مدیریت فناوری اطلاعات، امنیت فضای مجازی و شبکه دولت استانداری')
            ->assertSee('ADSL')->assertSee('CRM')->assertSee('سیماد ۳')->assertSee('VPN')
            ->assertSee('یک روز کاری')->assertSee('امکانات و مزایای سیستم')->assertSee('افزایش رضایت مشترکین')
            ->assertDontSee('اطلاعات این بخش هنوز منتشر نشده است.');
        $this->assertFalse(Setting::where('key', ServiceAgreementPages::IMPORT_KEY)->firstOrFail()->is_public);
        $this->getJson('/api/v1/settings')->assertOk()->assertDontSee(ServiceAgreementPages::IMPORT_KEY);
    }

    public function test_import_keeps_existing_ids_other_data_and_all_later_edits_and_deletions(): void
    {
        $tracking = $this->page('letter-tracking', ['metadata' => ['existing' => 'keep'], 'priority' => 12]);
        $network = $this->page('government-network-support');
        $other = $this->page('letter-tracking', ['slug' => 'unrelated-page', 'body' => '<p>Untouched</p>']);
        $accounts = User::pluck('password', 'id')->all();
        $roles = Role::pluck('permissions', 'id')->all();
        $result = app(ServiceAgreementPages::class)->import();
        $this->assertSame($tracking->id, $result['page_ids']['letter-tracking']);
        $this->assertSame($network->id, $result['page_ids']['government-network-support']);
        $this->assertSame('<p>Original text</p>', $result['previous']['letter-tracking']['body']);
        $this->assertSame('keep', $tracking->fresh()->metadata['existing']);
        $this->assertSame(12, $tracking->fresh()->priority);
        $this->assertSame('<p>Untouched</p>', $other->fresh()->body);
        $tracking->update(['body' => '<p>Later CMS edit</p>', 'title' => 'Edited title']);
        $network->delete();
        $this->assertTrue(app(ServiceAgreementPages::class)->import()['already_imported']);
        $this->assertSame('<p>Later CMS edit</p>', $tracking->fresh()->body);
        $this->assertSame('Edited title', $tracking->fresh()->title);
        $this->assertSoftDeleted('contents', ['id' => $network->id]);
        $this->assertSame(3, Content::withTrashed()->count());
        $this->assertSame($accounts, User::pluck('password', 'id')->all());
        $this->assertSame($roles, Role::pluck('permissions', 'id')->all());
    }

    public function test_admin_entries_open_the_correct_existing_editor_and_saved_text_reaches_the_public_url(): void
    {
        $result = app(ServiceAgreementPages::class)->import();
        $id = $result['page_ids']['letter-tracking'];
        $this->actingAs($this->admin, 'web');
        $this->get('/panel/admin')->assertOk()->assertSee('/panel/admin/letter-tracking', false)
            ->assertSee('/panel/admin/government-network-support', false);
        foreach (ServiceAgreementPages::PAGES as $key => $definition) {
            $this->get('/panel/admin/'.$key)->assertRedirect('/panel/admin/pages/'.$result['page_ids'][$key].'/edit');
            $this->get('/panel/admin/pages/'.$result['page_ids'][$key].'/edit')->assertOk()->assertSee('data-rich-text="true"', false);
        }
        $this->getJson('/web-api/v1/admin/contents/'.$id)->assertOk()->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.metadata.service_agreement', 'letter-tracking');
        $this->patchJson('/web-api/v1/admin/contents/'.$id, ['title' => 'Updated agreement', 'slug' => 'changed-by-admin',
            'body' => '<h2>Updated section</h2><p>Updated from the full-text editor.</p><script>alert(1)</script>'])->assertOk();
        $this->get('/section/menu-177')->assertOk()->assertSee('Updated agreement')->assertSee('Updated from the full-text editor.')
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/panel/admin/letter-tracking')->assertRedirect('/panel/admin/pages/'.$id.'/edit');
        $this->page('letter-tracking', ['body' => '<p>Another page reused the old slug.</p>']);
        $this->get('/section/menu-177')->assertOk()->assertSee('Updated from the full-text editor.')
            ->assertDontSee('Another page reused the old slug.');
        $this->assertSame(1, ServiceAgreementPages::query('letter-tracking', $this->admin->organization_id)->count());
        app(ServiceAgreementPages::class)->import();
        $this->assertSame('changed-by-admin', Content::findOrFail($id)->slug);
    }

    public function test_county_staff_cannot_manage_provincial_pages_and_county_page_content_stays_separate(): void
    {
        $ids = app(ServiceAgreementPages::class)->import()['page_ids'];
        $county = Organization::where('slug', 'county-amol')->firstOrFail();
        $user = User::create(['name' => 'County PR', 'username' => 'agreement_county_pr', 'password' => 'Fixture-Password42!',
            'organization_id' => $county->id, 'role_id' => Role::where('name', 'county_public_relations')->value('id'), 'person_type' => 'staff']);
        $this->actingAs($user, 'web');
        $this->get('/county-admin/amol')->assertOk()->assertDontSee('/county-admin/amol/letter-tracking', false)
            ->assertDontSee('/county-admin/amol/government-network-support', false);
        foreach (array_keys(ServiceAgreementPages::PAGES) as $key) $this->get('/county-admin/amol/'.$key)->assertForbidden();
        $this->patchJson('/web-api/v1/admin/contents/'.$ids['letter-tracking'], ['body' => 'Forbidden edit'])->assertNotFound();
        $this->get('/amol/section/menu-177')->assertOk()->assertDontSee('هدف از این توافق‌نامه');
        $this->page('letter-tracking', ['organization_id' => $county->id, 'author_id' => $user->id,
            'slug' => 'amol-agreement-page', 'body' => '<p>Amol-only agreement</p>',
            'metadata' => ['portal_path' => '/section/menu-177']]);
        $this->get('/amol/section/menu-177')->assertOk()->assertSee('Amol-only agreement')->assertDontSee('هدف از این توافق‌نامه');
        $this->get('/section/menu-177')->assertOk()->assertDontSee('Amol-only agreement')->assertSee('هدف از این توافق‌نامه');
    }

    public function test_unpublished_scheduled_expired_or_deleted_pages_do_not_fall_back_to_stale_text(): void
    {
        $id = app(ServiceAgreementPages::class)->import()['page_ids']['letter-tracking'];
        $page = Content::findOrFail($id);
        foreach ([['status' => 'draft'], ['status' => 'archived'], ['status' => 'published', 'published_at' => now()->addDay()],
            ['status' => 'published', 'published_at' => now()->subDay(), 'expires_at' => now()->subMinute()]] as $attributes) {
            $page->update($attributes);
            $this->get('/section/menu-177')->assertNotFound();
        }
        $page->delete();
        $this->get('/section/menu-177')->assertNotFound();
        $this->get('/section/menu-176')->assertOk();
    }

    public function test_import_rolls_back_both_pages_when_a_legacy_slug_belongs_to_a_county(): void
    {
        $county = Organization::where('slug', 'county-amol')->firstOrFail();
        $foreign = $this->page('government-network-support', ['organization_id' => $county->id]);
        $this->artisan('portal:import-service-agreements')->assertExitCode(1);
        $this->assertSame(1, Content::withTrashed()->count());
        $this->assertSame('<p>Original text</p>', $foreign->fresh()->body);
        $this->assertSame($county->id, $foreign->fresh()->organization_id);
        $this->assertFalse(Setting::where('key', ServiceAgreementPages::IMPORT_KEY)->exists());
    }

    public function test_import_does_not_restore_deleted_pages_and_admin_entry_points_require_staff_permissions(): void
    {
        $deleted = $this->page('government-network-support');
        $deleted->delete();
        $this->artisan('portal:import-service-agreements')->assertExitCode(1);
        $this->assertSoftDeleted('contents', ['id' => $deleted->id]);
        $this->assertSame(1, Content::withTrashed()->count());
        $this->get('/panel/admin/letter-tracking')->assertRedirect();
        $citizen = User::create(['name' => 'Citizen fixture', 'username' => 'agreement_citizen', 'password' => 'Fixture-Password42!',
            'person_type' => 'citizen', 'role_id' => Role::where('name', 'citizen')->value('id')]);
        $this->actingAs($citizen, 'web')->get('/panel/admin/letter-tracking')->assertForbidden();
        $operator = User::create(['name' => 'Operator fixture', 'username' => 'agreement_operator', 'password' => 'Fixture-Password42!',
            'person_type' => 'staff', 'organization_id' => $this->admin->organization_id, 'role_id' => Role::where('name', 'operator')->value('id')]);
        $this->actingAs($operator, 'web')->get('/panel/admin/government-network-support')->assertForbidden();
    }
}
