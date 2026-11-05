<?php

namespace Tests\Feature;

use App\Models\{Member, Organization, Role, Setting, StaffPortal, StaffPortalItem, User};
use App\Services\{MemberDirectory, StaffPages, StaffPortals};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class StaffPortalsTest extends TestCase
{
    use DatabaseMigrations;
    private StaffPortal $it;
    private StaffPortal $other;
    private User $manager;
    private User $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(MemberDirectory::class)->import();
        StaffPortals::sync();
        $this->it = StaffPortal::where('slug', 'it')->firstOrFail();
        $this->other = StaffPortal::whereKeyNot($this->it->id)->firstOrFail();
        foreach (['manager' => $this->it, 'second' => $this->other] as $property => $portal) {
            $this->$property = User::create(['username' => 'staff_fixture_'.$property, 'name' => 'Fixture '.$property, 'password' => 'Staff-Fixture42!',
                'role_id' => Role::where('name', StaffPortals::ROLE)->value('id'), 'organization_id' => $portal->member->organization_id,
                'person_type' => 'staff', 'access_scope' => 'organization', 'is_active' => true]);
            $portal->update(['owner_user_id' => $this->$property->id]);
        }
    }

    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function article(array $extra = []): array
    {
        return $extra + ['kind' => 'news', 'title' => 'خبر اختصاصی مدیر فناوری اطلاعات', 'body' => '<p>متن کامل خبر اختصاصی.</p>', 'summary' => '', 'status' => 'published', 'priority' => 0, 'asset_id' => null, 'published_at' => null];
    }

    public function test_all_staff_pages_have_public_links_and_import_is_non_destructive(): void
    {
        $this->assertSame(46, StaffPortal::count());
        $this->get('/managers?group=1')->assertOk()->assertSee('/staff/it', false)->assertSee('ورود به صفحه شخصی');
        $this->get('/staff/it')->assertOk()->assertSee(StaffPortals::IT_TITLE)->assertDontSee('ورود مدیر صفحه');
        $this->it->update(['title' => 'عنوان ویرایش‌شده', 'body' => '<p>متن مدیر</p>']);
        $before = User::orderBy('id')->pluck('password', 'id')->all();
        $this->assertSame(0, StaffPortals::sync());
        $this->assertSame('عنوان ویرایش‌شده', $this->it->fresh()->title);
        $this->assertSame($before, User::orderBy('id')->pluck('password', 'id')->all());
        $this->get('/staff/it?tab=about')->assertOk()->assertSee('متن مدیر');
        $this->get('/staff/not-a-manager')->assertNotFound();
        foreach (['/','/amol','/duties','/links','/panel/admin/login','/county-admin/amol/login','/login','/register'] as $path) $this->get($path)->assertOk();
    }

    public function test_login_is_bound_to_the_owning_manager_and_never_accepts_other_workspaces(): void
    {
        $this->get($this->it->adminPath())->assertRedirect('/staff-admin/it/login?error=auth_required');
        $this->post($this->it->adminPath().'/login', ['login' => $this->manager->username, 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->post($this->it->adminPath().'/login', ['login' => $this->second->username, 'password' => 'Staff-Fixture42!'])->assertForbidden();
        $this->assertGuest();
        $this->post($this->it->adminPath().'/login', ['login' => $this->manager->username, 'password' => 'Staff-Fixture42!'])->assertRedirect($this->it->adminPath());
        $this->assertAuthenticatedAs($this->manager);
        $this->get($this->it->adminPath())->assertOk()->assertSee('مشخصات صفحه');
        $this->get($this->other->adminPath())->assertForbidden();
        foreach (['/panel/admin', '/panel/admin/news', '/county-admin/amol'] as $path) $this->get($path)->assertForbidden();
        foreach (['admin/dashboard', 'admin/resources', 'admin/contents', 'admin/users', 'admin/custom-pages', 'media'] as $path) $this->getJson('/web-api/v1/'.$path)->assertForbidden();
        $this->postJson('/web-api/v1/media', [])->assertForbidden();
        $this->post('/staff-admin/it/logout')->assertRedirect('/staff-admin/it/login');
        $this->assertGuest();
    }

    public function test_every_cross_owner_operation_is_rejected_even_with_global_scope_and_wildcard_permissions(): void
    {
        $foreign = StaffPortals::saveItem($this->other, $this->article(['title' => 'خبر مدیر دوم']));
        $this->manager->update(['access_scope' => 'all']);
        $this->manager->role->update(['permissions' => ['*']]);
        $this->manager->unsetRelation('role');
        $this->as($this->manager);
        $this->assertFalse($this->manager->hasGlobalAccess());
        $this->assertFalse($this->manager->hasPermission('users.manage'));
        $this->get('/staff-admin/it/items/'.$foreign->id.'/edit')->assertNotFound();
        $this->post('/staff-admin/it/items/'.$foreign->id, $this->article())->assertNotFound();
        $this->post('/staff-admin/it/items/'.$foreign->id.'/delete')->assertNotFound();
        $this->post('/staff-admin/it/items/'.$foreign->id.'/restore')->assertNotFound();
        foreach (['id' => $this->other->id, 'staff_portal_id' => $this->other->id, 'owner_user_id' => $this->second->id, 'organization_id' => 1, 'slug' => 'forged'] as $field => $value)
            $this->post('/staff-admin/it/profile', ['title' => 'Forged', $field => $value])->assertForbidden();
        $this->getJson('/web-api/v1/staff-portals/'.$this->other->slug.'/assets')->assertForbidden();
        $this->postJson('/web-api/v1/staff-portals/'.$this->other->slug.'/assets', [])->assertForbidden();
        $this->assertSame('خبر مدیر دوم', $foreign->fresh()->title);
        foreach ([User::where('username', 'admin')->firstOrFail(), $this->second] as $user) {
            $this->as($user);
            $this->get('/staff-admin/it')->assertForbidden();
            $this->post('/staff-admin/it/items', $this->article())->assertForbidden();
        }
    }

    public function test_manager_profile_images_are_persistent_scoped_and_html_is_cleaned(): void
    {
        $this->as($this->manager);
        $image = $this->postJson('/web-api/v1/staff-portals/it/assets', ['file' => UploadedFile::fake()->image('portrait.png', 400, 500)])
            ->assertCreated()->json('data');
        $this->assertArrayNotHasKey('base64', $image);
        $this->get($image['url'])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->post('/staff-admin/it/profile', ['title' => 'واحد فناوری اطلاعات', 'summary' => '', 'body' => '<p>درباره واحد</p><script>alert(1)</script><img src="'.$image['url'].'">',
            'phone' => '', 'email' => '', 'address' => '', 'portrait_id' => $image['id'], 'banner_id' => ''])->assertRedirect('/staff-admin/it');
        $this->get('/staff/it?tab=about')->assertOk()->assertSee('درباره واحد')->assertDontSee('alert(1)', false)->assertSee($image['url'], false);
        $this->as($this->second);
        $this->post($this->other->adminPath().'/profile', ['title' => 'Foreign', 'portrait_id' => $image['id']])->assertNotFound();
        $this->post($this->other->adminPath().'/profile', ['title' => 'Foreign', 'body' => '<img src="'.$image['url'].'">'])->assertSessionHasErrors('body');
        $this->assertNull($this->other->fresh()->portrait_id);
        $this->as($this->manager);
        $this->post('/staff-admin/it/profile', ['title' => 'Unsafe', 'body' => '<img src="https://example.test/pixel.jpg">'])->assertSessionHasErrors('body');
        $this->postJson('/web-api/v1/staff-portals/it/assets', ['file' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')])->assertUnprocessable();
    }

    public function test_content_publication_scheduling_delete_restore_and_cross_page_reads(): void
    {
        $this->as($this->manager);
        $this->post('/staff-admin/it/items', $this->article(['status' => 'draft']))->assertRedirect('/staff-admin/it?tab=news');
        $item = $this->it->items()->firstOrFail();
        $this->get('/staff/it')->assertDontSee($item->title);
        $this->get('/staff/it/content/'.$item->id)->assertNotFound();
        $this->post('/staff-admin/it/items/'.$item->id, $this->article(['published_at' => now()->addDay()->toIso8601String()]))->assertRedirect();
        $this->get('/staff/it/content/'.$item->id)->assertNotFound();
        $this->post('/staff-admin/it/items/'.$item->id, $this->article(['published_at' => now()->subMinute()->toIso8601String()]))->assertRedirect();
        $this->get('/staff/it')->assertOk()->assertSee($item->title);
        $this->get('/staff/it/content/'.$item->id)->assertOk()->assertSee('متن کامل خبر اختصاصی.');
        $this->get($this->other->publicPath().'/content/'.$item->id)->assertNotFound();
        $this->post('/staff-admin/it/items/'.$item->id.'/delete')->assertRedirect();
        $this->get('/staff/it/content/'.$item->id)->assertNotFound();
        $this->get('/staff-admin/it?tab=news&trash=1')->assertOk()->assertSee('بازیابی')->assertSee($item->title);
        $this->post('/staff-admin/it/items/'.$item->id.'/restore')->assertRedirect();
        $this->get('/staff/it/content/'.$item->id)->assertOk();
        $this->manager->update(['is_active' => false]);
        $this->get('/staff-admin/it')->assertForbidden();
    }

    public function test_all_panel_tabs_and_gallery_batch_upload_work_without_affecting_province_news(): void
    {
        $this->as($this->manager);
        foreach (['profile', 'slide', 'pages', 'news', 'announcement', 'gallery', 'video', 'link', 'assets', 'account'] as $tab) $this->get('/staff-admin/it?tab='.$tab)->assertOk();
        foreach (array_keys(StaffPortals::KINDS) as $kind) $this->get('/staff-admin/it/items/new?kind='.$kind)->assertOk();
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'gallery', 'title' => 'آلبوم فعالیت‌ها', 'images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png')]]))->assertRedirect('/staff-admin/it?tab=gallery');
        $this->assertSame(2, $this->it->items()->where('kind', 'gallery')->count());
        $this->get('/staff/it?tab=gallery')->assertOk()->assertSee('آلبوم فعالیت‌ها');
        $this->get('/staff/it?tab=video')->assertOk();
        $this->get('/staff/it?tab=announcement')->assertOk();
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'video', 'url' => 'javascript:alert(1)']))->assertSessionHasErrors('url');
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'link', 'url' => 'https://example.test/service']))->assertRedirect();
        $this->get('/staff/it')->assertSee('https://example.test/service', false);
        $this->assertDatabaseMissing('contents', ['title' => 'آلبوم فعالیت‌ها']);
        $this->getJson('/api/v1/home')->assertOk()->assertDontSee('آلبوم فعالیت‌ها');
    }

    public function test_requested_test_account_bootstrap_has_no_default_password_and_never_resets_accounts(): void
    {
        config(['website.staff_demo_hash' => null]);
        $this->assertFalse(StaffPortals::bootstrapDemo());
        config(['website.staff_demo_hash' => Hash::make('Bootstrap-Fixture42!'), 'website.staff_demo_username' => 'staff_bootstrap_fixture']);
        $this->assertFalse(StaffPortals::bootstrapDemo()); // Existing owner is never replaced.
        $this->it->update(['owner_user_id' => null]);
        $this->assertTrue(StaffPortals::bootstrapDemo());
        $user = User::where('username', 'staff_bootstrap_fixture')->firstOrFail();
        $this->assertTrue(Hash::check('Bootstrap-Fixture42!', $user->password));
        $this->assertSame($user->id, $this->it->fresh()->owner_user_id);
        $user->update(['password' => 'Changed-Fixture55!']);
        $this->assertFalse(StaffPortals::bootstrapDemo());
        $this->assertTrue(Hash::check('Changed-Fixture55!', $user->fresh()->password));
        $this->assertTrue(Setting::where('key', 'imports.staff-portal-test-account.2026-10-30.v1')->where('is_public', false)->exists());
    }

    public function test_new_staff_directory_records_get_their_page_and_disabled_members_lose_panel_access(): void
    {
        $member = Member::create(['name' => 'مدیر ستادی جدید', 'position' => 'مدیر واحد', 'kind' => 'manager', 'locale' => 'fa',
            'organization_id' => $this->it->member->organization_id, 'category_id' => $this->it->member->category_id, 'is_active' => true]);
        $page = StaffPortal::where('member_id', $member->id)->firstOrFail();
        $this->get($page->publicPath())->assertOk()->assertSee('مدیر ستادی جدید');
        $page->update(['title' => 'عنوان اختصاصی']);
        $member->update(['name' => 'نام جدید مدیر']);
        $this->assertSame('عنوان اختصاصی', $page->fresh()->title);
        $this->as($this->manager);
        $this->it->member->update(['is_active' => false]);
        $this->get('/staff-admin/it')->assertNotFound();
        $this->get('/staff/it')->assertNotFound();
        $this->it->member->update(['is_active' => true]);
        $this->get('/staff-admin/it')->assertOk();
    }

    public function test_cold_start_skips_the_completed_import_and_manual_resync_handles_database_imports(): void
    {
        $this->assertTrue(StaffPortals::synchronized());
        $before = StaffPortal::count();
        \Illuminate\Support\Facades\DB::connection()->enableQueryLog();
        $this->assertSame(0, StaffPortals::sync());
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertFalse(collect($queries)->contains(fn($q)=>str_contains($q['query'],'from `members`')));
        $id = \Illuminate\Support\Facades\DB::table('members')->insertGetId(['name'=>'مدیر واردشده با پایگاه داده', 'position'=>'مدیر واحد', 'kind'=>'manager','locale'=>'fa',
            'organization_id'=>$this->it->member->organization_id,'category_id'=>$this->it->member->category_id,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame(0, StaffPortals::sync());
        $this->assertSame(1, StaffPortals::sync(true));
        $this->assertSame($before + 1, StaffPortal::count());
        $this->assertDatabaseHas('staff_portals',['member_id'=>$id]);
        $this->assertFalse(Setting::where('key',StaffPortals::SYNC_KEY)->firstOrFail()->is_public);
    }

    private function pageData(array $extra = []): array
    {
        return $extra + ['title' => 'خدمات واحد فناوری', 'path' => 'services', 'body' => '<p>متن اختصاصی صفحه واحد</p>',
            'nav_title' => 'خدمات واحد', 'show_in_navigation' => 1, 'status' => 'published', 'priority' => 1, 'published_at' => null];
    }

    public function test_default_staff_header_has_only_its_own_home_and_three_tabs_with_media_below(): void
    {
        $response = $this->get('/staff/it')->assertOk()->assertSee('data-staff-slideshow', false)->assertDontSee('ورود مدیر صفحه');
        $html = $response->getContent();
        preg_match('~<nav class="staff-site-navigation".*?</nav>~s', $html, $nav);
        $this->assertSame(1, substr_count($nav[0], '<a '));
        $this->assertStringContainsString('href="/staff/it"', $nav[0]);
        preg_match('~<nav class="staff-public-tabs".*?</nav>~s', $html, $tabs);
        $this->assertSame(3, substr_count($tabs[0], '<button '));
        $this->assertStringNotContainsString('gallery', $tabs[0]);
        $this->assertStringNotContainsString('video', $tabs[0]);
        $this->assertLessThan(strpos($html, 'id="staff-gallery"'), strpos($html, 'class="staff-public-tabs"'));
        $this->assertLessThan(strpos($html, 'id="staff-videos"'), strpos($html, 'id="staff-gallery"'));
        $this->get($this->other->publicPath())->assertOk()->assertSee('href="'.$this->other->publicPath().'"', false);
        $this->get('/')->assertOk()->assertDontSee('staff-site-navigation');
        $this->get('/amol')->assertOk()->assertDontSee('staff-site-navigation');
    }

    public function test_custom_pages_are_owned_published_and_added_to_only_their_managers_menu(): void
    {
        $this->as($this->manager);
        $this->get('/staff-admin/it/pages/new')->assertOk()->assertSee('نمایش در منو');
        $this->post('/staff-admin/it/pages', $this->pageData())->assertRedirect('/staff-admin/it?tab=pages');
        $page = $this->it->pages()->firstOrFail();
        $this->get('/staff-admin/it?tab=pages')->assertOk()->assertSee($page->title);
        $this->get('/staff-admin/it/pages/'.$page->id.'/edit')->assertOk()->assertSee($page->path);
        $this->get('/staff/it/pages/services')->assertOk()->assertSee('متن اختصاصی صفحه واحد')->assertSee('aria-current="page">خدمات واحد', false);
        $this->get('/staff/it')->assertSee('href="/staff/it/pages/services"', false);
        $this->get($this->other->publicPath())->assertDontSee('/staff/it/pages/services', false);
        $this->get($this->other->publicPath().'/pages/services')->assertNotFound();
        $this->post('/staff-admin/it/pages/'.$page->id, $this->pageData(['show_in_navigation' => 0]))->assertRedirect();
        $this->get('/staff/it')->assertDontSee('/staff/it/pages/services', false);
        $this->get('/staff/it/pages/services')->assertOk();
        $this->post('/staff-admin/it/pages/'.$page->id, $this->pageData(['status' => 'draft']))->assertRedirect();
        $this->get('/staff/it/pages/services')->assertNotFound();
        $this->post('/staff-admin/it/pages/'.$page->id, $this->pageData(['published_at' => now()->addDay()->toIso8601String()]))->assertRedirect();
        $this->get('/staff/it/pages/services')->assertNotFound();
        $this->get('/staff/it')->assertDontSee('/staff/it/pages/services', false);
        $this->post('/staff-admin/it/pages/'.$page->id, $this->pageData(['published_at' => now()->subMinute()->toIso8601String()]))->assertRedirect();
        $this->get('/staff/it/pages/services')->assertOk();
        $this->post('/staff-admin/it/pages/'.$page->id.'/delete')->assertRedirect();
        $this->get('/staff/it/pages/services')->assertNotFound();
        $this->get('/staff/it')->assertDontSee('/staff/it/pages/services', false);
        $this->post('/staff-admin/it/pages', $this->pageData())->assertSessionHasErrors('path');
        $this->get('/staff-admin/it?tab=pages&trash=1')->assertOk()->assertSee('بازیابی');
        $this->post('/staff-admin/it/pages/'.$page->id.'/restore')->assertRedirect();
        $this->get('/staff/it/pages/services')->assertOk();
    }

    public function test_page_routes_menu_order_and_images_cannot_escape_the_owner(): void
    {
        $foreign = StaffPages::save($this->other, $this->pageData());
        $foreignImage = StaffPortals::upload($this->other, UploadedFile::fake()->image('other.png'));
        $this->as($this->manager);
        foreach (['/edit', ''] as $suffix) {
            if ($suffix) $this->get('/staff-admin/it/pages/'.$foreign->id.$suffix)->assertNotFound();
            else $this->post('/staff-admin/it/pages/'.$foreign->id, $this->pageData())->assertNotFound();
        }
        foreach (['delete','restore'] as $action) $this->post('/staff-admin/it/pages/'.$foreign->id.'/'.$action)->assertNotFound();
        $this->post('/staff-admin/it/pages', $this->pageData(['staff_portal_id' => $this->other->id]))->assertForbidden();
        $this->post('/staff-admin/it/pages', $this->pageData(['body' => '<p>تصویر</p><img src="'.$foreignImage->url.'">']))->assertSessionHasErrors('body');
        foreach (['../services', 'folder/page', 'javascript:alert(1)', 'a?x=1', ['bad']] as $path)
            $this->post('/staff-admin/it/pages', $this->pageData(['path' => $path]))->assertSessionHasErrors('path');
        $this->post('/staff-admin/it/pages', $this->pageData(['path' => 'تماس', 'body' => '<p>متن تماس</p><script>alert(1)</script>']))->assertRedirect();
        $this->get('/staff/it/pages/'.rawurlencode('تماس'))->assertOk()->assertSee('متن تماس')->assertDontSee('alert(1)', false);
        StaffPages::save($this->it, $this->pageData(['path' => 'second', 'nav_title' => 'پیوند دوم', 'priority' => 20]));
        StaffPages::save($this->it, $this->pageData(['path' => 'first', 'nav_title' => 'پیوند اول', 'priority' => 10]));
        $this->assertSame(['تماس','first','second'], StaffPages::navigation($this->it)->pluck('path')->all());
        $this->assertSame('خدمات واحد فناوری', $foreign->fresh()->title);
        $this->as(User::where('username','admin')->firstOrFail());
        $this->post('/staff-admin/it/pages', $this->pageData())->assertForbidden();
    }

    public function test_slides_are_manageable_scheduled_and_keep_assets_isolated(): void
    {
        $ownImage = StaffPortals::upload($this->it, UploadedFile::fake()->image('slide.png', 1280, 480));
        $foreignImage = StaffPortals::upload($this->other, UploadedFile::fake()->image('other.png'));
        $this->as($this->manager);
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'slide']))->assertSessionHasErrors('asset_id');
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'slide', 'asset_id' => $foreignImage->id]))->assertNotFound();
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'slide', 'asset_id' => $ownImage->id, 'url' => 'javascript:alert(1)']))->assertSessionHasErrors('url');
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'slide', 'title' => 'اسلاید منتشرشده واحد', 'asset_id' => $ownImage->id, 'url' => 'https://example.test/article']))->assertRedirect('/staff-admin/it?tab=slide');
        $this->post('/staff-admin/it/items', $this->article(['kind' => 'slide', 'title' => 'اسلاید بعدی', 'asset_id' => $ownImage->id]))->assertRedirect();
        StaffPortals::saveItem($this->it, $this->article(['kind'=>'slide','title'=>'اسلاید آینده','asset_id'=>$ownImage->id,'published_at'=>now()->addDay()]));
        StaffPortals::saveItem($this->it, $this->article(['kind'=>'slide','title'=>'اسلاید پیش‌نویس','asset_id'=>$ownImage->id,'status'=>'draft']));
        $response = $this->get('/staff/it')->assertOk()->assertSee('اسلاید منتشرشده واحد')->assertSee('اسلاید قبلی')->assertSee('اسلاید بعدی')->assertSee('https://example.test/article',false)->assertDontSee('اسلاید آینده')->assertDontSee('اسلاید پیش‌نویس');
        $this->get($this->other->publicPath())->assertDontSee('اسلاید منتشرشده واحد');
        $slide = $this->it->items()->where('title','اسلاید منتشرشده واحد')->firstOrFail();
        $this->post('/staff-admin/it/items/'.$slide->id.'/delete')->assertRedirect();
        $this->get('/staff/it')->assertDontSee('اسلاید منتشرشده واحد');
        $this->post('/staff-admin/it/items/'.$slide->id.'/restore')->assertRedirect();
        $this->get('/staff/it')->assertSee('اسلاید منتشرشده واحد');
    }

    public function test_preparation_upgrades_an_existing_installation_without_resetting_owners_or_content(): void
    {
        $article = StaffPortals::saveItem($this->it, $this->article());
        $password = $this->manager->password;
        // Simulate the previous deployed schema: staff portals exist and were already synchronized.
        \Illuminate\Support\Facades\Schema::drop('staff_portal_pages');
        \Illuminate\Support\Facades\DB::table('migrations')->where('migration','2026_10_30_000002_create_staff_portal_pages')->delete();
        $this->assertTrue(StaffPortals::synchronized());
        $this->assertFalse(StaffPages::ready());
        $this->get('/staff/it')->assertOk()->assertSee('data-staff-slideshow',false);
        $this->artisan('portal:prepare-staff-portals')->assertSuccessful();
        $this->assertTrue(StaffPages::ready());
        $page = StaffPages::save($this->it, $this->pageData());
        $this->artisan('portal:prepare-staff-portals')->assertSuccessful();
        $this->assertSame($this->manager->id,$this->it->fresh()->owner_user_id);
        $this->assertSame($password,$this->manager->fresh()->password);
        $this->assertSame('خبر اختصاصی مدیر فناوری اطلاعات',$article->fresh()->title);
        $this->assertSame('خدمات واحد فناوری',$page->fresh()->title);
        $this->get('/staff/it/pages/services')->assertOk();
    }
}
