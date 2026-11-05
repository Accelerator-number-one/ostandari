<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Content;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($this->admin);
        Storage::fake('public');
    }

    private function image(): array
    {
        return $this->post('/api/v1/media', ['visibility' => 'public', 'file' => UploadedFile::fake()->image('fixture.png', 16, 12)], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    public function test_one_current_governor_can_be_edited_with_a_photo_and_reaches_the_home_page(): void
    {
        $image = $this->image();
        $id = $this->postJson('/api/v1/admin/members', ['name' => 'Fixture governor', 'position' => 'استاندار مازندران', 'kind' => 'governor', 'is_current' => true, 'is_active' => true, 'media_id' => $image['id']])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.governor.id', $id)->assertJsonPath('data.governor.image.url', $image['url']);
        $this->postJson('/api/v1/admin/members', ['name' => 'Duplicate governor', 'position' => 'استاندار', 'kind' => 'governor'])->assertUnprocessable()->assertJsonValidationErrors('is_current');
        $this->patchJson('/api/v1/admin/members/'.$id, ['name' => 'Updated governor'])->assertOk();
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.governor.name', 'Updated governor');
        $historical = $this->postJson('/api/v1/admin/members', ['name' => 'Historical governor', 'position' => 'استاندار پیشین', 'kind' => 'governor', 'is_current' => false])->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/members/'.$historical, ['is_current' => true])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/members/'.$id, ['is_current' => false])->assertOk();
        $this->patchJson('/api/v1/admin/members/'.$historical, ['is_current' => true])->assertOk();
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('data.governor.id', $historical);
    }

    public function test_current_governor_uniqueness_is_enforced_by_mysql_and_restore_checks_conflicts(): void
    {
        $payload = ['kind' => 'governor', 'position' => 'Governor', 'is_active' => true, 'is_current' => true];
        $first = Member::create($payload + ['name' => 'First governor']);
        try {
            DB::table('members')->insert($payload + ['name' => 'Concurrent duplicate', 'locale' => 'fa']);
            $this->fail('MySQL must reject a second current governor even outside the controller.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('members_one_current_governor', $exception->getMessage());
        }
        $this->deleteJson('/api/v1/admin/members/'.$first->id)->assertNoContent();
        $second = Member::create($payload + ['name' => 'Second governor']);
        $this->postJson('/api/v1/admin/members/'.$first->id.'/restore')->assertUnprocessable();
        $this->assertSoftDeleted('members', ['id' => $first->id]);
        $this->assertSame($second->id, $this->getJson('/api/v1/home')->json('data.governor.id'));
        $this->postJson('/api/v1/admin/members', $payload + ['name' => 'Duplicate governor in another locale', 'locale' => 'en'])->assertUnprocessable();
    }

    public function test_governor_cannot_be_assigned_to_a_county_as_the_provincial_governor(): void
    {
        $organization = Organization::create(['name' => 'Fixture county', 'slug' => 'fixture-county', 'type' => 'department']);
        $this->postJson('/api/v1/admin/members', ['name' => 'Wrong governor', 'position' => 'Governor', 'kind' => 'governor', 'organization_id' => $organization->id])->assertUnprocessable();
    }

    public function test_images_and_tables_survive_sanitization_and_used_inline_images_cannot_be_deleted(): void
    {
        $image = $this->image();
        $body = '<h2>عنوان</h2><p>متن فارسی <strong>مهم</strong></p><img src="'.$image['url'].'" alt="تصویر خبر" onerror="alert(1)"><table><caption>جدول نمونه</caption><thead><tr><th scope="col">ستون</th></tr></thead><tbody><tr><td>مقدار</td></tr></tbody></table><script>alert(1)</script>';
        $response = $this->postJson('/api/v1/admin/news', ['title' => 'CMS fixture', 'body' => $body, 'status' => 'published'])->assertCreated();
        $id = $response->json('data.id');
        $clean = $response->json('data.body');
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString($image['url'], $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('جدول نمونه', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->getJson('/api/v1/contents/'.$id)->assertOk()->assertJsonPath('data.content.body', $clean);
        $this->deleteJson('/api/v1/media/'.$image['id'])->assertConflict();
        $this->patchJson('/api/v1/admin/news/'.$id, ['body' => '<p>تصویر برداشته شد.</p>', 'media_ids' => []])->assertOk();
        $this->deleteJson('/api/v1/media/'.$image['id'])->assertNoContent();
    }

    public function test_missing_or_private_inline_images_are_rejected_and_member_images_are_tracked(): void
    {
        $this->postJson('/api/v1/admin/news', ['title' => 'Missing image', 'body' => '<img src="'.Storage::disk('public')->url('missing.png').'">'])->assertUnprocessable();
        $image = $this->image();
        $id = $this->postJson('/api/v1/admin/members', ['name' => 'Member with rich biography', 'position' => 'Manager', 'kind' => 'manager', 'body' => '<p>Biography</p><img src="'.$image['url'].'">'])->assertCreated()->json('data.id');
        $this->deleteJson('/api/v1/media/'.$image['id'])->assertConflict();
        $this->patchJson('/api/v1/admin/members/'.$id, ['body' => 'No image'])->assertOk();
        $this->deleteJson('/api/v1/media/'.$image['id'])->assertNoContent();
    }

    public function test_related_choices_work_without_granting_management_permission_or_exposing_other_units(): void
    {
        $own = Organization::create(['name' => 'Own unit', 'slug' => 'own-unit', 'type' => 'department']);
        $other = Organization::create(['name' => 'Other unit', 'slug' => 'other-unit', 'type' => 'department']);
        $editor = User::create(['name' => 'Editor fixture', 'username' => 'editor-fixture', 'password' => 'Fixture-Only-Password42!', 'person_type' => 'staff', 'role_id' => Role::where('name', 'editor')->value('id'), 'organization_id' => $own->id, 'access_scope' => 'organization', 'is_active' => true]);
        Sanctum::actingAs($editor);
        $this->getJson('/api/v1/admin/organizations')->assertForbidden();
        $response = $this->getJson('/api/v1/admin/lookups/organizations')->assertOk();
        $this->assertSame([$own->id], array_column($response->json('data'), 'id'));
        $this->assertNotContains($other->id, array_column($response->json('data'), 'id'));
        $this->assertArrayNotHasKey('email', $response->json('data.0'));
        $this->getJson('/api/v1/admin/lookups/users')->assertForbidden();
        $this->getJson('/api/v1/admin/lookups/roles')->assertForbidden();
        $this->getJson('/api/v1/admin/lookups/unknown')->assertNotFound();
    }

    public function test_reply_and_status_update_are_atomic_for_original_contact_and_governor_editors(): void
    {
        $ticket = Ticket::create(['reference' => (string) \Illuminate\Support\Str::ulid(), 'organization_id' => $this->admin->organization_id, 'type' => 'contact', 'name' => 'Fixture citizen', 'email' => 'fixture@example.test', 'subject' => 'Fixture subject', 'body' => 'Fixture report body', 'status' => 'review']);
        $this->patchJson('/api/v1/admin/tickets/'.$ticket->reference, ['status' => 'resolved', 'reply' => str_repeat('x', 20001)])->assertUnprocessable();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'review']);
        $this->assertDatabaseCount('ticket_messages', 0);
        $this->patchJson('/api/v1/admin/tickets/'.$ticket->reference, ['status' => 'resolved', 'reply' => 'پاسخ آزمایشی کامل'])->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'resolved']);
        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'پاسخ آزمایشی کامل', 'is_internal' => false]);
    }

    public function test_new_slogan_setting_is_public_and_admin_user_search_matches_username(): void
    {
        $this->putJson('/api/v1/admin/settings', ['settings' => [['key' => 'site.slogan_text', 'value' => 'شعار مورد آزمایش', 'is_public' => true]]])->assertOk();
        $this->assertSame('شعار مورد آزمایش', $this->getJson('/api/v1/settings')->json('data')['site.slogan_text']);
        $this->getJson('/api/v1/admin/users?q=admin')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->admin->id);
    }

    public function test_dashboard_activity_is_organization_scoped_and_has_a_bounded_query_cost(): void
    {
        $own = Organization::create(['name' => 'Dashboard own unit', 'slug' => 'dashboard-own', 'type' => 'department']);
        $other = Organization::create(['name' => 'Dashboard other unit', 'slug' => 'dashboard-other', 'type' => 'department']);
        $operator = User::create(['name' => 'Dashboard operator', 'username' => 'dashboard-operator', 'password' => 'Fixture-Only-Password42!', 'person_type' => 'staff', 'role_id' => Role::where('name', 'operator')->value('id'), 'organization_id' => $own->id, 'access_scope' => 'organization', 'is_active' => true]);
        foreach ([$own, $other] as $organization) {
            Content::create(['title' => 'Dashboard fixture', 'slug' => 'dashboard-'.$organization->id, 'type' => 'news', 'body' => 'Fixture body', 'author_id' => $this->admin->id, 'organization_id' => $organization->id]);
            Ticket::create(['reference' => (string) \Illuminate\Support\Str::ulid(), 'type' => 'report', 'subject' => 'Scoped report', 'body' => 'Scoped report fixture', 'organization_id' => $organization->id]);
        }
        Sanctum::actingAs($operator);
        DB::flushQueryLog(); DB::enableQueryLog();
        try {
            $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $activity = $response->json('data.daily_activity');
        $this->assertCount(10, $activity);
        $today = array_pop($activity);
        $this->assertSame(1, $today['contents']);
        $this->assertSame(1, $today['tickets']);
        $this->assertSame(1, $response->json('data.contents_by_type.news'));
        $this->assertCount(1, $response->json('data.recent_tickets'));
        $this->assertLessThan(20, $queries, 'Dashboard loading must not issue a separate pair of queries for every day.');
    }
}
