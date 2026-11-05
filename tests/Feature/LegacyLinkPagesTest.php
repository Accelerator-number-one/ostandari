<?php

namespace Tests\Feature;

use App\Models\{Content, CustomPage, Link, Menu, MenuItem, Organization, Role, Service, Setting, Slide, User};
use App\Services\{LegacyLinkPages, ManagerPages};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyLinkPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $province;
    private User $county;
    private string $source = 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=900001&OrgId=1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->firstOrFail();
        foreach (['province', 'county'] as $kind) {
            $org = $kind === 'province' ? $this->admin->organization : Organization::where('slug', 'county-amol')->firstOrFail();
            $this->$kind = User::create(['name' => $kind, 'username' => 'legacy_'.$kind, 'password' => 'Fixture-Only-Password42!',
                'person_type' => 'staff', 'organization_id' => $org->id, 'access_scope' => 'organization',
                'role_id' => Role::where('name', $kind === 'province' ? 'province_public_relations' : 'county_public_relations')->value('id')]);
        }
    }

    private function link(?int $owner, string $url, string $title = 'صفحه آزمایشی'): Link
    {
        return Link::create(['organization_id' => $owner, 'locale' => 'fa', 'title' => $title,
            'url' => $url, 'position' => 'useful', 'is_active' => true, 'priority' => 1]);
    }

    private function prepare(): CustomPage
    {
        $this->link($this->admin->organization_id, $this->source);
        $this->artisan('portal:prepare-legacy-link-pages')->assertExitCode(0);
        return CustomPage::where('legacy_source_key', LegacyLinkPages::key($this->source))->firstOrFail();
    }

    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
    }

    public function test_preparation_creates_blank_pages_and_localizes_only_exact_old_domain_references(): void
    {
        $root = $this->admin->organization_id;
        $first = $this->link($root, $this->source, 'خدمت آزمایشی');
        $alias = $this->link(null, 'http://www.ostan-mz.ir/Home/ShowDetailsMenuContent?OrgId=1&MenuId=900001');
        $external = $this->link($root, 'https://moi.ir/');
        $pdf = $this->link($root, ManagerPages::PDF_URL);
        $foreign = $this->link($this->county->organization_id, 'https://ostan-mz.ir/foreign-county');
        $menu = Menu::create(['organization_id' => $root, 'key' => 'legacy-fixture', 'name' => 'منو', 'locale' => 'fa']);
        $item = MenuItem::create(['menu_id' => $menu->id, 'title' => 'خدمت منو', 'url' => $this->source]);
        $service = Service::create(['organization_id' => $root, 'name' => 'خدمت', 'slug' => 'legacy-service', 'url' => $this->source]);
        $slide = Slide::create(['organization_id' => $root, 'kind' => 'slide', 'title' => 'اسلاید', 'url' => $this->source]);
        Setting::create(['key' => 'site.footer', 'is_public' => true, 'value' => ['links' => [['title' => 'پاورقی', 'url' => $this->source],
            ['title' => 'سایت دیگر', 'url' => 'https://moi.ir/']], 'image' => ['url' => 'https://ostan-mz.ir/photo.jpg']]]);
        Setting::create(['key' => 'site.logo', 'is_public' => true, 'value' => ['url' => 'https://ostan-mz.ir/logo.png']]);
        $accounts = User::pluck('password', 'id')->all();
        $roles = Role::pluck('permissions', 'id')->all();
        $this->artisan('portal:prepare-legacy-link-pages')->assertExitCode(0);
        $page = CustomPage::where('legacy_source_key', LegacyLinkPages::key($this->source))->sole();
        $this->assertSame('', $page->body);
        $this->assertSame('published', $page->status);
        $this->assertSame('خدمت آزمایشی', $page->title);
        $this->assertSame($root, $page->organization_id);
        foreach ([$first, $alias, $item, $service, $slide] as $record) $this->assertSame($page->path, $record->fresh()->url);
        $this->assertSame('https://moi.ir/', $external->fresh()->url);
        $this->assertSame(ManagerPages::PDF_URL, $pdf->fresh()->url);
        $this->assertSame('https://ostan-mz.ir/foreign-county', $foreign->fresh()->url);
        $footer = Setting::where('key', 'site.footer')->value('value');
        $this->assertSame($page->path, $footer['links'][0]['url']);
        $this->assertSame('https://moi.ir/', $footer['links'][1]['url']);
        $this->assertSame('https://ostan-mz.ir/photo.jpg', $footer['image']['url']);
        $this->assertSame(['url' => 'https://ostan-mz.ir/logo.png'], Setting::where('key', 'site.logo')->value('value'));
        $this->assertSame($accounts, User::pluck('password', 'id')->all());
        $this->assertSame($roles, Role::pluck('permissions', 'id')->all());
        $this->as($this->admin);
        $this->patchJson('/web-api/v1/admin/services/'.$service->id, ['url' => $page->path])->assertOk();
        $this->patchJson('/web-api/v1/admin/slides/'.$slide->id, ['url' => $page->path])->assertOk();
        $this->get($page->path)->assertOk()->assertSee('خدمت آزمایشی')->assertSee('data-source-footer', false);
    }

    public function test_provincial_pr_can_edit_the_blank_page_and_county_pr_cannot_access_it(): void
    {
        $page = $this->prepare();
        $this->as($this->province);
        $this->get('/panel/admin/custom-pages?tab=list')->assertOk()->assertSee('صفحه‌های ساخته‌شده');
        $this->getJson('/web-api/v1/admin/custom-pages/'.$page->id)->assertOk()->assertJsonPath('data.body', '');
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$page->id, ['title' => 'عنوان ویرایش‌شده'])->assertOk();
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$page->id, ['body' => '<h2>متن واردشده</h2><p>متن اصلی صفحه</p><script>alert(1)</script>'])->assertOk();
        $this->get($page->path)->assertOk()->assertSee('متن واردشده')->assertSee('متن اصلی صفحه')->assertDontSee('<script>alert(1)</script>', false);
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$page->id, ['path' => '/different-path'])->assertUnprocessable();
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$page->id, ['body' => ''])->assertOk();
        $this->as($this->county);
        $this->getJson('/web-api/v1/admin/custom-pages/'.$page->id)->assertNotFound();
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$page->id, ['body' => 'دسترسی غیرمجاز'])->assertNotFound();
        $this->deleteJson('/web-api/v1/admin/custom-pages/'.$page->id)->assertNotFound();
        $this->get('/amol'.$page->path)->assertNotFound();
    }

    public function test_reruns_keep_edits_archives_and_trash_without_duplicate_pages(): void
    {
        $page = $this->prepare();
        $count = CustomPage::withTrashed()->count();
        $page->update(['body' => '<p>حفظ این محتوا</p>', 'title' => 'عنوان مدیر', 'status' => 'archived']);
        $this->link($this->admin->organization_id, $this->source.'#details');
        $this->artisan('portal:prepare-legacy-link-pages')->assertExitCode(0);
        $this->assertSame($count, CustomPage::withTrashed()->count());
        $this->assertSame('<p>حفظ این محتوا</p>', $page->fresh()->body);
        $this->assertSame('عنوان مدیر', $page->fresh()->title);
        $this->assertSame('archived', $page->fresh()->status);
        $this->get($page->path)->assertNotFound();
        $page->delete();
        $this->link($this->admin->organization_id, $this->source);
        $this->artisan('portal:prepare-legacy-link-pages')->assertExitCode(0);
        $this->assertSame($count, CustomPage::withTrashed()->count());
        $this->assertNotNull(CustomPage::withTrashed()->find($page->id)->deleted_at);
    }

    public function test_links_inside_existing_rich_text_are_discovered_without_replacing_its_content(): void
    {
        $body = '<p>متن قبلی <a href='.$this->source.'>خدمت داخل متن</a><img src="https://ostan-mz.ir/photo.jpg"></p>';
        $content = Content::create(['organization_id' => $this->admin->organization_id, 'author_id' => $this->admin->id,
            'type' => 'news', 'locale' => 'fa', 'slug' => 'legacy-rich-text-fixture', 'title' => 'خبر قبلی',
            'body' => $body, 'status' => 'published', 'published_at' => now()->subMinute()]);
        $this->artisan('portal:prepare-legacy-link-pages')->assertExitCode(0);
        $page = CustomPage::where('legacy_source_key', LegacyLinkPages::key($this->source))->sole();
        $this->assertSame('', $page->body);
        $this->assertSame('خدمت داخل متن', $page->title);
        $this->assertSame($body, $content->fresh()->body);
        $this->get('/content/'.$content->id)->assertOk()->assertSee('href="'.$page->path.'"', false)->assertSee('متن قبلی');
    }
}
