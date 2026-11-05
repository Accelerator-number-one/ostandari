<?php

namespace Tests\Feature;

use App\Models\{Category, Member, Organization, Role, Setting, StaffPortal, User};
use App\Services\{MemberDirectory, StaffPages, StaffPortals};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class GovernorPersonalPortalsTest extends TestCase
{
    use DatabaseMigrations;

    private StaffPortal $governor;
    private StaffPortal $other;
    private User $owner;
    private User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(MemberDirectory::class)->import();
        StaffPortals::sync();
        [$this->governor, $this->other] = StaffPortal::whereHas('member', fn ($q) => $q->where('kind', 'county_governor'))->orderBy('id')->take(2)->get()->all();
        foreach (['owner' => $this->governor, 'otherOwner' => $this->other] as $key => $portal) {
            $this->$key = User::create(['username' => 'governor_fixture_'.$key, 'name' => $portal->member->name,
                'password' => 'Governor-Fixture42!', 'role_id' => Role::where('name', StaffPortals::ROLE)->value('id'),
                'organization_id' => $portal->member->organization_id, 'person_type' => 'staff', 'access_scope' => 'organization', 'is_active' => true]);
            $portal->update(['owner_user_id' => $this->$key->id]);
        }
    }

    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function item(array $extra = []): array
    {
        return $extra + ['kind' => 'news', 'title' => 'خبر اختصاصی فرماندار', 'body' => '<p>متن خبر فرماندار</p>',
            'summary' => '', 'status' => 'published', 'priority' => 0, 'asset_id' => null, 'published_at' => null];
    }

    private function page(array $extra = []): array
    {
        return $extra + ['title' => 'فعالیت‌های فرماندار', 'path' => 'activities', 'body' => '<p>متن صفحه اختصاصی فرماندار</p>',
            'nav_title' => 'فعالیت‌ها', 'show_in_navigation' => 1, 'status' => 'published', 'priority' => 0, 'published_at' => null];
    }

    public function test_governors_have_personal_links_and_all_member_cards_omit_contact_buttons(): void
    {
        $this->assertSame(46, StaffPortal::count());
        $this->assertSame(22, StaffPortal::whereHas('member', fn ($q) => $q->where('kind', 'county_governor'))->count());
        $this->assertTrue($this->governor->isGovernor());
        $this->get('/managers?group=4')->assertOk()->assertSee($this->governor->publicPath(), false)->assertSee('ورود به صفحه شخصی');
        foreach ([1, 2, 3, 4] as $group) $this->get('/managers?group='.$group)->assertOk()->assertDontSee('portal-member-contact', false);
        $this->get($this->governor->publicPath())->assertOk()->assertSee('معرفی فرماندار')->assertSee('staff-site-navigation', false)
            ->assertSee('<title>'.$this->governor->member->name.' | درگاه الکترونیکی استانداری مازندران</title>', false);
        // Deputies and executive directors do not acquire a personal workspace.
        foreach ([2, 3] as $group) {
            $category = Category::where('slug', MemberDirectory::GROUPS[$group]['slug'])->firstOrFail();
            $this->assertFalse(StaffPortal::whereIn('member_id', Member::where('category_id', $category->id)->select('id'))->exists());
        }
    }

    public function test_upgrade_adds_governors_without_resetting_staff_content_accounts_or_urls(): void
    {
        $staff = StaffPortal::where('slug', 'it')->firstOrFail();
        $staff->update(['title' => 'عنوان حفظ‌شده', 'body' => '<p>متن حفظ‌شده</p>']);
        $article = StaffPortals::saveItem($staff, $this->item());
        $password = $this->owner->password;
        Setting::where('key', StaffPortals::SYNC_KEY)->delete();
        Setting::firstOrCreate(['key' => 'imports.staff-portals.2026-10-30.v1'], ['value' => [], 'is_public' => false]);
        $missing = StaffPortal::whereHas('member', fn ($q) => $q->where('kind', 'county_governor'))->whereNull('owner_user_id')->get();
        StaffPortal::whereKey($missing->modelKeys())->delete();
        $this->assertSame($missing->count(), StaffPortals::sync());
        $this->assertSame(0, StaffPortals::sync());
        $this->assertSame('عنوان حفظ‌شده', $staff->fresh()->title);
        $this->assertSame('it', $staff->fresh()->slug);
        $this->assertSame('خبر اختصاصی فرماندار', $article->fresh()->title);
        $this->assertSame($this->owner->id, $this->governor->fresh()->owner_user_id);
        $this->assertSame($password, $this->owner->fresh()->password);
    }

    public function test_governor_login_and_all_owner_operations_reject_other_personal_and_public_workspaces(): void
    {
        $base = $this->governor->adminPath();
        $foreign = StaffPortals::saveItem($this->other, $this->item());
        $foreignPage = StaffPages::save($this->other, $this->page());
        $foreignImage = StaffPortals::upload($this->other, UploadedFile::fake()->image('other.png'));
        $this->get($base)->assertRedirect($base.'/login?error=auth_required');
        $this->post($base.'/login', ['login' => $this->otherOwner->username, 'password' => 'Governor-Fixture42!'])->assertForbidden();
        $this->assertGuest();
        $this->post($base.'/login', ['login' => $this->owner->username, 'password' => 'Governor-Fixture42!'])->assertRedirect($base);
        $this->assertAuthenticatedAs($this->owner);
        $this->get($base)->assertOk();
        $this->owner->update(['access_scope' => 'all']);
        $this->owner->role->update(['permissions' => ['*']]);
        $this->owner->unsetRelation('role');
        $this->assertFalse($this->owner->hasGlobalAccess());
        $this->assertFalse($this->owner->hasPermission('users.manage'));
        foreach ([$this->other->adminPath(), '/staff-admin/it', '/panel/admin', '/county-admin/amol'] as $path) $this->get($path)->assertForbidden();
        foreach (['admin/contents', 'admin/users', 'admin/custom-pages', 'media'] as $path) $this->getJson('/web-api/v1/'.$path)->assertForbidden();
        foreach (['items' => [$foreign->id, $this->item()], 'pages' => [$foreignPage->id, $this->page()]] as $resource => [$id, $data]) {
            $this->get($base.'/'.$resource.'/'.$id.'/edit')->assertNotFound();
            $this->post($base.'/'.$resource.'/'.$id, $data)->assertNotFound();
            foreach (['delete', 'restore'] as $action) $this->post($base.'/'.$resource.'/'.$id.'/'.$action)->assertNotFound();
        }
        $this->post($base.'/profile', ['title' => 'تغییر غیرمجاز', 'owner_user_id' => $this->otherOwner->id])->assertForbidden();
        $this->post($base.'/items', $this->item(['staff_portal_id' => $this->other->id]))->assertForbidden();
        $this->post($base.'/profile', ['title' => 'تصویر دیگران', 'portrait_id' => $foreignImage->id])->assertNotFound();
        $this->post($base.'/pages', $this->page(['body' => '<img src="'.$foreignImage->url.'">']))->assertSessionHasErrors('body');
        $this->getJson('/web-api/v1/staff-portals/'.$this->other->slug.'/assets')->assertForbidden();
        $this->postJson('/web-api/v1/staff-portals/'.$this->other->slug.'/assets', [])->assertForbidden();
        $this->get($this->governor->publicPath().'/content/'.$foreign->id)->assertNotFound();
        $this->assertSame('خبر اختصاصی فرماندار', $foreign->fresh()->title);
        $this->as(User::where('username', 'admin')->firstOrFail());
        $this->get($base)->assertForbidden();
        $this->post($base.'/profile', ['title' => 'تغییر غیرمجاز'])->assertForbidden();
        $this->as($this->owner);
        $this->governor->member->update(['is_active' => false]);
        $this->get($base)->assertNotFound();
        $this->get($this->governor->publicPath())->assertNotFound();
    }

    public function test_governor_can_manage_profile_slides_both_feeds_photos_videos_links_and_navigation(): void
    {
        $this->as($this->owner);
        $base = $this->governor->adminPath();
        $public = $this->governor->publicPath();
        foreach (['profile', 'slide', 'pages', 'news', 'announcement', 'gallery', 'video', 'link', 'assets', 'account'] as $tab)
            $this->get($base.'?tab='.$tab)->assertOk();
        $image = $this->postJson('/web-api/v1/staff-portals/'.$this->governor->slug.'/assets', ['file' => UploadedFile::fake()->image('portrait.png', 600, 400)])
            ->assertCreated()->json('data');
        $this->get($image['url'])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->post($base.'/profile', ['title' => 'عنوان اختصاصی فرماندار', 'body' => '<p>معرفی فرماندار آزمایشی</p>', 'portrait_id' => $image['id']])->assertRedirect($base);
        foreach (array_keys(StaffPortals::KINDS) as $kind) {
            $title = 'محتوای '.$kind.' فرماندار';
            $data = $this->item(['kind' => $kind, 'title' => $title, 'asset_id' => $image['id'], 'url' => in_array($kind, ['video', 'link', 'slide']) ? 'https://example.test/'.$kind : null]);
            $this->get($base.'/items/new?kind='.$kind)->assertOk();
            $this->post($base.'/items', $data)->assertRedirect($base.'?tab='.$kind);
            $item = $this->governor->items()->where('kind', $kind)->firstOrFail();
            $this->get($public)->assertOk()->assertSee($title);
            $this->get($this->other->publicPath())->assertDontSee($title);
            $this->post($base.'/items/'.$item->id, $data + ['summary' => ''])->assertRedirect();
            $this->post($base.'/items/'.$item->id.'/delete')->assertRedirect();
            $this->get($public)->assertDontSee($title);
            $this->post($base.'/items/'.$item->id.'/restore')->assertRedirect();
            $this->get($public)->assertSee($title);
        }
        $this->post($base.'/items', $this->item(['kind' => 'gallery', 'title' => 'آلبوم فرماندار', 'images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png')]]))->assertRedirect();
        $this->assertSame(3, $this->governor->items()->where('kind', 'gallery')->count());
        $this->post($base.'/pages', $this->page())->assertRedirect($base.'?tab=pages');
        $page = $this->governor->pages()->firstOrFail();
        $this->get($public)->assertSee($page->publicPath(), false)->assertSee('معرفی فرماندار آزمایشی');
        $this->get($page->publicPath())->assertOk()->assertSee('متن صفحه اختصاصی فرماندار');
        $this->get($this->other->publicPath())->assertDontSee($page->publicPath(), false);
        $this->get($this->other->publicPath().'/pages/activities')->assertNotFound();
        $this->post($base.'/pages/'.$page->id, $this->page(['show_in_navigation' => 0]))->assertRedirect();
        $this->get($public)->assertDontSee($page->publicPath(), false);
        $this->get($page->publicPath())->assertOk();
        $this->post($base.'/pages/'.$page->id.'/delete')->assertRedirect();
        $this->get($page->publicPath())->assertNotFound();
        $this->post($base.'/pages/'.$page->id.'/restore')->assertRedirect();
        $this->get($page->publicPath())->assertOk();
        $this->assertDatabaseMissing('contents', ['title' => 'محتوای news فرماندار']);
    }

    public function test_staff_owners_and_province_and_county_public_relations_cannot_manage_a_governors_personal_page(): void
    {
        $staff = StaffPortal::where('slug', 'it')->firstOrFail();
        foreach ([StaffPortals::ROLE, 'province_public_relations', 'county_public_relations'] as $role) {
            $organization = $role === 'county_public_relations' ? Organization::where('type', 'county')->firstOrFail() : $this->governor->member->organization;
            $user = User::create(['username' => 'isolated_fixture_'.$role, 'name' => 'حساب آزمون', 'password' => 'Isolated-Fixture42!',
                'role_id' => Role::where('name', $role)->value('id'), 'organization_id' => $organization->id,
                'person_type' => 'staff', 'access_scope' => 'organization', 'is_active' => true]);
            if ($role === StaffPortals::ROLE) $staff->update(['owner_user_id' => $user->id]);
            $this->as($user);
            $base = $this->governor->adminPath();
            $this->get($base)->assertForbidden();
            $this->post($base.'/profile', ['title' => 'تغییر غیرمجاز'])->assertForbidden();
            $this->getJson('/web-api/v1/staff-portals/'.$this->governor->slug.'/assets')->assertForbidden();
        }
    }

    public function test_tab_feeds_are_prerendered_separately_and_default_slides_have_no_automatic_name_caption(): void
    {
        StaffPortals::saveItem($this->governor, $this->item(['title' => 'خبر عمومی فرماندار']));
        StaffPortals::saveItem($this->governor, $this->item(['kind' => 'announcement', 'title' => 'اطلاعیه عمومی فرماندار']));
        StaffPortals::saveItem($this->governor, $this->item(['title' => 'خبر پیش‌نویس فرماندار', 'status' => 'draft']));
        StaffPortals::saveItem($this->governor, $this->item(['kind' => 'announcement', 'title' => 'اطلاعیه آینده فرماندار', 'published_at' => now()->addDay()]));
        $response = $this->get($this->governor->publicPath().'?tab=announcement')->assertOk()
            ->assertSee('خبر عمومی فرماندار')->assertSee('اطلاعیه عمومی فرماندار')->assertDontSee('خبر پیش‌نویس فرماندار')->assertDontSee('اطلاعیه آینده فرماندار');
        $this->assertSame(['news', 'announcement'], array_keys($response->viewData('staffFeeds')));
        foreach ($response->viewData('staffSlides') as $slide) {
            $this->assertFalse($slide['caption']);
            $this->assertSame('', $slide['title']);
        }
        preg_match('~<nav class="staff-public-tabs".*?</nav>~s', $response->getContent(), $tabs);
        $this->assertSame(3, substr_count($tabs[0], '<button '));
        $this->assertStringNotContainsString('href=', $tabs[0]);
        $this->assertStringContainsString('aria-selected="true"', $tabs[0]);
        $this->assertStringNotContainsString('staff-slide-caption', $response->getContent());
    }

    public function test_account_command_creates_only_one_governor_owner_and_never_replaces_an_existing_account(): void
    {
        $portal = StaffPortal::whereHas('member', fn ($q) => $q->where('kind', 'county_governor'))->whereNull('owner_user_id')->firstOrFail();
        $this->artisan('portal:staff-manager', ['slug' => $portal->slug, '--username' => 'governor_created_fixture'])
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'Governor-Created42!')->assertSuccessful();
        $user = User::where('username', 'governor_created_fixture')->firstOrFail();
        $this->assertSame($user->id, $portal->fresh()->owner_user_id);
        $this->post($portal->adminPath().'/login', ['login' => $user->username, 'password' => 'Governor-Created42!'])->assertRedirect($portal->adminPath());
        $this->get($this->governor->adminPath())->assertForbidden();
        $this->artisan('portal:staff-manager', ['slug' => $portal->slug, '--username' => 'another_fixture'])->assertFailed();
        $this->assertDatabaseMissing('users', ['username' => 'another_fixture']);
        $this->assertSame($user->id, $portal->fresh()->owner_user_id);
    }
}
