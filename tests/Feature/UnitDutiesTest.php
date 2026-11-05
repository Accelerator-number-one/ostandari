<?php

namespace Tests\Feature;

use App\Models\{Content, Organization, Role, Setting, User};
use App\Services\UnitDuties;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class UnitDutiesTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_only_complete_official_texts_are_published_and_all_clauses_reach_their_public_pages(): void
    {
        $this->artisan('portal:import-unit-duties')->assertExitCode(0);
        $source = UnitDuties::catalog();
        $this->assertCount(24, $source['units']);
        $this->assertCount(6, $source['groups']);
        $report = UnitDuties::report();
        $this->assertCount(11, $report['content_ids']);
        $this->assertCount(13, $report['pending_source_ids']);
        $this->assertSame(11, Content::where('type', 'duty')->count());
        $listing = $this->get('/duties')->assertOk()->assertSee('portal-duties-directory', false);
        foreach ($source['units'] as $unit) {
            if ($unit['status'] !== 'complete') {
                $this->assertContains($unit['source_id'], $report['pending_source_ids']);
                $this->assertArrayNotHasKey($unit['source_id'], $report['content_ids']);
                continue;
            }
            $id = $report['content_ids'][$unit['source_id']];
            $record = Content::findOrFail($id);
            $listing->assertSee($unit['title']);
            $this->assertSame($unit['group'], $record->duty_group);
            $this->assertSame(hash('sha256', $unit['source_text']), $record->metadata['source_sha256']);
            $this->assertSame($unit['clause_count'], substr_count($record->body, '<li>'));
            $this->assertStringNotContainsString('دسترسی آسان', $record->body);
            $this->assertStringNotContainsString('مشاهدهٔ اطلاعات رسمی', $record->body);
            foreach ([$unit['legacy_path'], '/duties/'.$id] as $path) {
                $response = $this->get($path)->assertOk()->assertSee('portal-duty-detail', false)->assertSee($unit['title']);
                $this->assertStringContainsString(trim(strip_tags($record->body)), trim(strip_tags(html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
            }
        }
        $this->assertFalse(Setting::where('key', UnitDuties::IMPORT_KEY)->firstOrFail()->is_public);
        $this->getJson('/api/v1/settings')->assertDontSee(UnitDuties::IMPORT_KEY);
    }

    public function test_existing_page_ids_and_editor_texts_are_kept_and_imports_never_undo_later_edits_or_deletions(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $legacy = Content::create(['organization_id' => $admin->organization_id, 'author_id' => $admin->id, 'type' => 'page', 'locale' => 'fa',
            'slug' => 'official-page-menu-49', 'title' => 'شرح وظایف و ماموریتهای اساسی استانداری ها',
            'body' => '<p><a href="https://ostan-mz.ir/">مشاهدهٔ اطلاعات رسمی شرح وظایف و ماموریتهای اساسی استانداری ها در صفحهٔ منبع</a></p>',
            'status' => 'published', 'published_at' => now()->subDay(), 'metadata' => ['content_completeness' => 'source_reference', 'keep' => 'yes']]);
        $editor = Content::create(['organization_id' => $admin->organization_id, 'author_id' => $admin->id, 'type' => 'page', 'locale' => 'fa',
            'slug' => 'official-page-menu-50', 'title' => 'عنوان ویرایش‌شده', 'body' => '<p>متن ثبت‌شده توسط مدیر</p>', 'status' => 'draft']);
        $passwords = User::pluck('password', 'id')->all();
        $result = app(UnitDuties::class)->import();
        $this->assertSame($legacy->id, $result['content_ids'][49]);
        $this->assertSame('yes', $legacy->fresh()->metadata['keep']);
        $this->assertSame('duty', $legacy->fresh()->type);
        $this->assertSame('<p>متن ثبت‌شده توسط مدیر</p>', $editor->fresh()->body);
        $this->assertSame('draft', $editor->fresh()->status);
        $this->get('/section/menu-50')->assertNotFound();
        $legacy->update(['title' => 'عنوان جدید', 'body' => '<p>ویرایش بعدی مدیر</p>', 'metadata' => [...$legacy->fresh()->metadata, 'duty_group' => 'گروه تازه']]);
        $remove = Content::findOrFail($result['content_ids'][44]);
        $remove->delete();
        $this->assertTrue(app(UnitDuties::class)->import()['already_imported']);
        $this->assertSame('<p>ویرایش بعدی مدیر</p>', $legacy->fresh()->body);
        $this->assertSame('گروه تازه', $legacy->fresh()->duty_group);
        $this->assertSoftDeleted($remove);
        $this->get('/section/menu-44')->assertNotFound();
        $this->get('/section/menu-49')->assertOk()->assertSee('ویرایش بعدی مدیر');
        $this->assertSame($passwords, User::pluck('password', 'id')->all());
    }

    public function test_admin_can_create_edit_and_remove_a_unit_with_a_custom_group_through_the_existing_dashboard(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin, 'web');
        $this->get('/panel/admin/duties/new')->assertOk()->assertSee('نام واحد')->assertSee('data-field="duty_group"', false)->assertSee('data-rich-text="true"', false);
        $this->get('/panel/admin/duties/new?unit=190')->assertOk()->assertSee('معاونت توسعه مدیریت و منابع انسانی');
        $id = $this->postJson('/web-api/v1/admin/contents', ['type' => 'duty', 'title' => 'واحد آزمایشی', 'body' => '<ol><li>وظیفه نخست</li><li>وظیفه دوم</li></ol>',
            'duty_group' => 'گروه جدید', 'metadata' => ['keep' => 'yes'], 'status' => 'published', 'published_at' => now()->subMinute()->toDateTimeString()])->assertCreated()->assertJsonPath('data.duty_group', 'گروه جدید')->assertJsonPath('data.metadata.keep', 'yes')->json('data.id');
        $this->get('/duties')->assertOk()->assertSee('واحد آزمایشی')->assertSee('گروه جدید');
        $this->patchJson('/web-api/v1/admin/contents/'.$id, ['duty_group' => 'گروه ویرایش‌شده', 'body' => '<p>شرح ویرایش‌شده</p>'])->assertOk()->assertJsonPath('data.metadata.keep', 'yes');
        $this->get('/duties/'.$id)->assertOk()->assertSee('گروه ویرایش‌شده')->assertSee('شرح ویرایش‌شده');
        $this->patchJson('/web-api/v1/admin/contents/'.$id, ['duty_group' => str_repeat('x', 256)])->assertUnprocessable();
        $this->patchJson('/web-api/v1/admin/contents/'.$id, ['metadata' => ['duty_group' => ['invalid']]])->assertUnprocessable();
        $this->postJson('/web-api/v1/admin/contents', ['type' => 'news', 'title' => 'خبر', 'body' => '<p>خبر</p>', 'duty_group' => 'گروه واحد'])->assertUnprocessable();
        $this->deleteJson('/web-api/v1/admin/contents/'.$id)->assertNoContent();
        $this->get('/duties')->assertDontSee('واحد آزمایشی');
    }

    public function test_imported_province_duties_are_kept_out_of_county_pages_and_county_editors_cannot_change_them(): void
    {
        $result = app(UnitDuties::class)->import();
        $org = Organization::where('type', 'county')->whereHas('county', fn ($q) => $q->where('slug', 'amol'))->firstOrFail();
        $user = User::create(['name' => 'County duties editor', 'username' => 'duties-editor', 'password' => 'Fixture-Only42!', 'person_type' => 'staff', 'is_active' => true,
            'organization_id' => $org->id, 'access_scope' => 'organization', 'role_id' => Role::where('name', 'county_public_relations')->value('id')]);
        $this->actingAs($user, 'web');
        $id = $result['content_ids'][49];
        $this->patchJson('/web-api/v1/admin/contents/'.$id, ['body' => '<p>Forbidden</p>'])->assertNotFound();
        $this->get('/amol/duties')->assertOk()->assertDontSee('ماموریتهای اساسی استانداری');
        $this->get('/amol/duties/'.$id)->assertNotFound();
        $own = $this->postJson('/web-api/v1/admin/contents', ['type' => 'duty', 'title' => 'وظایف ویژه آمل', 'body' => '<p>شرح وظایف آمل</p>', 'duty_group' => 'واحدهای آمل',
            'status' => 'published', 'published_at' => now()->subMinute()->toDateTimeString()])->assertCreated()->json('data.id');
        $this->get('/amol/duties/'.$own)->assertOk()->assertSee('شرح وظایف آمل');
        $this->get('/duties')->assertDontSee('وظایف ویژه آمل');
    }
}
