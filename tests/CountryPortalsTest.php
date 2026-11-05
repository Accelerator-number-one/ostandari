<?php

namespace Tests\Feature;

use App\Models\{Category, Content, Gallery, Media, Menu, Organization, Poll, Role, Ticket, User};
use App\Services\ResourceRegistry;
use Database\Seeders\{DatabaseSeeder, PortalRolesSeeder};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Storage};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CountyPortalsTest extends TestCase
{
    use RefreshDatabase;
    private User $own;
    private User $other;
    private Organization $province;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Organization::where('type', 'governorate')->firstOrFail();
        $role = Role::where('name', 'county_public_relations')->firstOrFail();
        foreach (['amol' => 'own', 'nur' => 'other'] as $slug => $field) {
            $org = Organization::whereHas('county', fn ($q) => $q->where('slug', $slug))->where('type', 'county')->firstOrFail();
            $this->$field = User::create(['name' => $slug.' PR', 'username' => $slug.'_fixture', 'password' => 'Fixture-Password42!', 'person_type' => 'staff', 'organization_id' => $org->id, 'role_id' => $role->id, 'access_scope' => 'organization', 'is_active' => true]);
        }
        Sanctum::actingAs($this->own);
    }

    private function image(User $owner): Media
    {
        return Media::create(['user_id' => $owner->id, 'organization_id' => $owner->organization_id, 'disk' => 'public', 'path' => 'fixture/'.$owner->id.'.webp', 'original_name' => 'fixture.webp', 'mime' => 'image/webp', 'size' => 12, 'visibility' => 'public']);
    }

    private function fixtures(User $owner): array
    {
        $attributes = ['organization_id' => $owner->organization_id];
        return [
            'menu' => Menu::create($attributes + ['name' => 'Fixture', 'key' => 'fixture-'.$owner->id]),
            'poll' => Poll::create($attributes + ['question' => 'Fixture']),
            'gallery' => Gallery::create($attributes + ['title' => 'Fixture', 'slug' => 'fixture-'.$owner->id]),
            'category' => Category::create($attributes + ['name' => 'Fixture', 'slug' => 'fixture-'.$owner->id, 'type' => 'news']),
            'image' => $this->image($owner),
        ];
    }

    public function test_all_sixteen_cms_resources_isolate_crud_reorder_and_restore(): void
    {
        $own = $this->fixtures($this->own); $other = $this->fixtures($this->other);
        $payloads = fn ($f) => [
            'contents' => ['type' => 'news', 'title' => 'Fixture news', 'body' => 'Fixture body'],
            'categories' => ['type' => 'news', 'name' => 'Fixture category'], 'tags' => ['name' => 'Fixture tag'],
            'menus' => ['name' => 'Fixture menu', 'key' => 'same-key-per-county'],
            'menu-items' => ['menu_id' => $f['menu']->id, 'title' => 'Fixture', 'url' => '/news'],
            'slides' => ['title' => 'Fixture slide'], 'links' => ['title' => 'Fixture', 'url' => 'https://example.test/'],
            'faqs' => ['question' => 'Fixture', 'answer' => 'Fixture'], 'quotes' => ['author' => 'Fixture', 'text' => 'Fixture'],
            'galleries' => ['title' => 'Fixture gallery'], 'gallery-items' => ['gallery_id' => $f['gallery']->id, 'media_id' => $f['image']->id],
            'polls' => ['question' => 'Fixture'], 'poll-options' => ['poll_id' => $f['poll']->id, 'title' => 'Fixture'],
            'forms' => ['title' => 'Fixture form', 'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]]],
            'services' => ['name' => 'Fixture service'], 'members' => ['name' => 'Fixture governor', 'position' => 'Governor', 'kind' => 'county_governor'],
        ];
        foreach ($payloads($own) as $resource => $payload) {
            $this->travel(1)->minutes(); // Keep normal throttling enabled, across realistic request windows.
            Sanctum::actingAs($this->other);
            $foreign = $this->postJson('/api/v1/admin/'.$resource, $payloads($other)[$resource])->assertCreated()->json('data.id');
            Sanctum::actingAs($this->own);
            $id = $this->postJson('/api/v1/admin/'.$resource, $payload)->assertCreated()->json('data.id');
            $ids = $this->getJson('/api/v1/admin/'.$resource)->assertOk()->json('data.*.id');
            $this->assertContains($id, $ids); $this->assertNotContains($foreign, $ids);
            foreach (['GET', 'PATCH', 'DELETE'] as $method) $this->json($method, '/api/v1/admin/'.$resource.'/'.$foreign, $payload)->assertNotFound();
            $this->getJson('/api/v1/admin/'.$resource.'/'.$id)->assertOk();
            $this->patchJson('/api/v1/admin/'.$resource.'/'.$id, $payload)->assertOk();
            $def = ResourceRegistry::get($resource);
            if (isset($def['rules']['priority'])) {
                $this->postJson('/api/v1/admin/'.$resource.'/reorder', ['items' => [['id' => $id, 'priority' => 9], ['id' => $foreign, 'priority' => 9]]])->assertNotFound();
                $this->assertSame(0, (int) $def['model']::find($id)->priority);
                $this->postJson('/api/v1/admin/'.$resource.'/reorder', ['items' => [['id' => $id, 'priority' => 7]]])->assertOk();
            }
            $this->deleteJson('/api/v1/admin/'.$resource.'/'.$id)->assertNoContent();
            if (in_array(SoftDeletes::class, class_uses_recursive($def['model']), true)) {
                Sanctum::actingAs($this->other); $this->deleteJson('/api/v1/admin/'.$resource.'/'.$foreign)->assertNoContent();
                Sanctum::actingAs($this->own);
                $this->postJson('/api/v1/admin/'.$resource.'/'.$foreign.'/restore')->assertNotFound();
                $this->postJson('/api/v1/admin/'.$resource.'/'.$id.'/restore')->assertOk();
            }
        }
    }

    public function test_forged_organization_and_cross_county_relations_are_rejected(): void
    {
        $f = $this->fixtures($this->other);
        foreach ([['organization_id' => $this->other->organization_id], ['category_id' => $f['category']->id], ['cover_media_id' => $f['image']->id]] as $foreign) {
            $this->postJson('/api/v1/admin/contents', $foreign + ['type' => 'news', 'title' => 'Fixture', 'body' => 'Fixture body'])->assertForbidden();
        }
        $this->postJson('/api/v1/admin/menu-items', ['menu_id' => $f['menu']->id, 'title' => 'Fixture', 'url' => '/news'])->assertForbidden();
        $this->postJson('/api/v1/admin/poll-options', ['poll_id' => $f['poll']->id, 'title' => 'Fixture'])->assertForbidden();
        $this->postJson('/api/v1/admin/gallery-items', ['gallery_id' => $f['gallery']->id, 'media_id' => $f['image']->id])->assertForbidden();
        $this->postJson('/api/v1/admin/members', ['name' => 'Fixture', 'position' => 'Governor', 'kind' => 'county_governor', 'county_id' => $this->other->organization->county_id])->assertForbidden();
        $this->postJson('/api/v1/admin/members', ['name' => 'Fixture', 'position' => 'Governor', 'kind' => 'governor'])->assertForbidden();
        $this->postJson('/api/v1/admin/organizations', ['name' => 'Forged', 'type' => 'county', 'parent_id' => $this->own->organization_id])->assertForbidden();
        $this->patchJson('/api/v1/admin/organizations/'.$this->own->organization_id, ['county_id' => $this->other->organization->county_id])->assertForbidden();
        $this->patchJson('/api/v1/admin/organizations/'.$this->own->organization_id, ['phone' => '01112345678'])->assertOk();
    }

    public function test_lookups_dashboard_media_and_exports_only_include_own_county(): void
    {
        $own = $this->fixtures($this->own); $foreign = $this->fixtures($this->other);
        foreach ([$this->own, $this->other] as $owner) {
            Content::create(['author_id' => $owner->id, 'organization_id' => $owner->organization_id, 'type' => 'news', 'slug' => 'fixture-'.$owner->id, 'title' => 'County '.$owner->id, 'body' => 'Fixture']);
            Ticket::create(['reference' => 'REPORT-'.$owner->id, 'user_id' => $owner->id, 'organization_id' => $owner->organization_id, 'type' => 'report', 'subject' => 'Fixture', 'body' => 'Fixture']);
        }
        foreach (['menus' => 'menu', 'polls' => 'poll', 'galleries' => 'gallery', 'categories' => 'category'] as $resource => $key) {
            $ids = $this->getJson('/api/v1/admin/lookups/'.$resource)->assertOk()->json('data.*.id');
            $this->assertContains($own[$key]->id, $ids); $this->assertNotContains($foreign[$key]->id, $ids);
        }
        $this->assertSame([$this->own->organization_id], $this->getJson('/api/v1/admin/lookups/organizations')->assertOk()->json('data.*.id'));
        $this->assertSame([$this->own->organization->county_id], $this->getJson('/api/v1/admin/lookups/counties')->assertOk()->json('data.*.id'));
        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.workspace.county.slug', 'amol')->assertJsonPath('data.contents_by_type.news', 1)->assertJsonCount(1, 'data.recent_reports');
        $this->getJson('/api/v1/media')->assertOk()->assertJsonMissing(['id' => $foreign['image']->id]);
        $this->deleteJson('/api/v1/media/'.$foreign['image']->id)->assertNotFound();
        $csv = $this->getJson('/api/v1/admin/exports/contents')->assertOk()->streamedContent();
        $this->assertStringContainsString('County '.$this->own->id, $csv); $this->assertStringNotContainsString('County '.$this->other->id, $csv);
        foreach (['settings', 'users', 'roles', 'subscribers'] as $resource) $this->getJson('/api/v1/admin/'.$resource)->assertForbidden();
        $legacy = Role::where('name', 'editor')->firstOrFail(); $legacy->update(['permissions' => ['*']]);
        $this->other->update(['role_id' => $legacy->id, 'access_scope' => 'all']);
        $this->patchJson('/api/v1/admin/reports/REPORT-'.$this->own->id, ['assigned_to' => $this->other->id])->assertUnprocessable();
    }

    public function test_legacy_wildcard_pr_cannot_escape_and_provincial_pr_is_read_only(): void
    {
        $editor = Role::where('name', 'editor')->firstOrFail(); $editor->update(['permissions' => ['*']]);
        $this->own->update(['role_id' => $editor->id, 'access_scope' => 'all']); $this->own->unsetRelation('role');
        Sanctum::actingAs($this->own);
        $this->getJson('/api/v1/admin/lookups/organizations')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->own->update(['organization_id' => $this->province->id]); $this->own->unsetRelation('organization');
        Sanctum::actingAs($this->own);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.portal_context.can_write', false);
        foreach (['contents', 'slides', 'forms'] as $resource) $this->postJson('/api/v1/admin/'.$resource, [])->assertForbidden();
        $this->postJson('/api/v1/admin/contents/reorder', [])->assertForbidden();
        $this->deleteJson('/api/v1/admin/contents/1')->assertForbidden();
        $this->postJson('/api/v1/media', [])->assertForbidden();
        $this->seed(PortalRolesSeeder::class);
        $this->assertSame('province_public_relations', $this->own->fresh()->role->name);
        $this->assertSame('organization', $this->own->fresh()->access_scope);
    }

    public function test_wrong_county_login_fails_before_a_token_is_minted(): void
    {
        $before = $this->own->tokens()->count();
        $this->postJson('/api/v1/auth/login', ['login' => $this->own->username, 'password' => 'Fixture-Password42!', 'county_slug' => 'nur'])->assertForbidden();
        $this->assertSame($before, $this->own->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['login' => $this->own->username, 'password' => 'Fixture-Password42!', 'county_slug' => 'amol'])->assertOk()->assertJsonPath('data.user.portal_context.dashboard_path', '/county-admin/amol');
    }

    public function test_published_county_content_only_appears_in_its_own_public_portal(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        foreach ([$admin, $this->own, $this->other] as $owner) Content::create(['author_id' => $owner->id, 'organization_id' => $owner->organization_id, 'type' => 'news', 'slug' => 'published-'.$owner->id, 'title' => 'Portal '.$owner->id, 'body' => 'Fixture', 'status' => 'published', 'published_at' => now()->subMinute()]);
        $this->assertSame(['Portal '.$admin->id], $this->getJson('/api/v1/home')->assertOk()->json('data.news.*.title'));
        $this->assertSame(['Portal '.$this->own->id], $this->getJson('/api/v1/portals/'.$this->own->organization->slug)->assertOk()->json('data.news.*.title'));
        $article = Content::where('organization_id', $this->own->organization_id)->firstOrFail();
        $this->getJson('/api/v1/contents/'.$article->id)->assertOk()->assertJsonCount(0, 'data.related');
    }

    public function test_media_upload_owner_is_pinned_and_inline_media_reuse_cannot_cross_counties(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jT1sAAAAASUVORK5CYII='));
        $id = $this->post('/api/v1/media', ['file' => $file, 'visibility' => 'public', 'organization_id' => $this->other->organization_id], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.organization_id', $this->own->organization_id)->json('data.id');
        $colleague = User::create(['name' => 'Colleague', 'username' => 'colleague_fixture', 'password' => 'Fixture-Password42!', 'person_type' => 'staff', 'organization_id' => $this->own->organization_id, 'role_id' => $this->own->role_id]);
        Sanctum::actingAs($colleague);
        $this->getJson('/api/v1/media')->assertOk()->assertJsonFragment(['id' => $id]);
        $this->postJson('/api/v1/admin/contents', ['type' => 'news', 'title' => 'Shared within county', 'body' => 'Fixture', 'cover_media_id' => $id])->assertCreated();
        Sanctum::actingAs($this->other);
        $this->getJson('/api/v1/media/'.$id.'/download')->assertNotFound();
        $this->postJson('/api/v1/admin/contents', ['type' => 'news', 'title' => 'Foreign inline', 'body' => '<p>Fixture</p><img src="'.Media::findOrFail($id)->url.'" alt="Fixture">'])->assertForbidden();
    }

    public function test_an_existing_session_stops_when_its_organization_or_county_is_disabled(): void
    {
        $this->own->organization->update(['is_active' => false]); $this->own->unsetRelation('organization');
        $this->getJson('/api/v1/auth/me')->assertForbidden(); $this->getJson('/api/v1/admin/contents')->assertForbidden(); $this->getJson('/api/v1/media')->assertForbidden();
        $this->own->organization->update(['is_active' => true]); $this->own->organization->county->update(['is_active' => false]); $this->own->unsetRelation('organization');
        $this->postJson('/api/v1/auth/login', ['login' => $this->own->username, 'password' => 'Fixture-Password42!', 'county_slug' => 'amol'])->assertForbidden();
    }

    public function test_account_command_binds_a_county_and_preserves_existing_accounts_and_central_admin(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail(); $hash = $admin->password;
        $prompt = 'Password (at least 12 characters, mixed case and numbers)';
        $this->artisan('portal:public-relations amol --username=new_county_fixture')->expectsQuestion($prompt, 'Fixture-Password42!')->assertExitCode(0);
        $created = User::where('username', 'new_county_fixture')->firstOrFail();
        $this->assertSame('county_public_relations', $created->role->name); $this->assertSame($this->own->organization_id, $created->organization_id); $this->assertTrue(Hash::check('Fixture-Password42!', $created->password));
        $this->artisan('portal:public-relations nur --username=new_county_fixture')->expectsQuestion($prompt, 'Another-Password42!')->assertExitCode(1);
        $this->assertSame($this->own->organization_id, $created->fresh()->organization_id);
        $this->assertSame($hash, $admin->fresh()->password); $this->assertSame('super_admin', $admin->fresh()->role->name);
        $this->artisan('portal:public-relations --province --username=new_province_fixture')->expectsQuestion($prompt, 'Fixture-Password42!')->assertExitCode(0);
        $this->assertTrue(User::where('username', 'new_province_fixture')->firstOrFail()->hasReadOnlyWorkspace());
    }
}
