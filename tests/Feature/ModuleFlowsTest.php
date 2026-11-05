<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DynamicForm;
use App\Models\Gallery;
use App\Models\Menu;
use App\Models\Poll;
use App\Models\Role;
use App\Models\User;
use App\Services\PortalCalendar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function citizen(): User
    {
        return User::create(['name' => 'Citizen', 'role_id' => Role::where('name', 'citizen')->value('id')]);
    }

    public function test_each_administration_module_accepts_persists_updates_and_lists_its_contract(): void
    {
        Sanctum::actingAs($this->admin());
        Storage::fake('public');
        $media = $this->post('/api/v1/media', ['visibility' => 'public', 'file' => UploadedFile::fake()->image('sample.png', 16, 12)], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $menu = Menu::create(['name' => 'Main', 'key' => 'main']);
        $gallery = Gallery::create(['title' => 'G', 'slug' => 'g', 'locale' => 'fa']);
        $poll = Poll::create(['question' => 'Q']);
        $fixtures = [
            'categories' => ['name' => 'Category', 'type' => 'document'], 'tags' => ['name' => 'Tag'], 'counties' => ['name' => 'County'], 'organizations' => ['name' => 'Org', 'type' => 'department'],
            'menus' => ['name' => 'Footer', 'key' => 'footer'], 'menu-items' => ['menu_id' => $menu->id, 'title' => 'Item', 'url' => '/news'],
            'slides' => ['title' => 'Slide', 'media_id' => $media], 'advertisements' => ['title' => 'Ad', 'media_id' => $media],
            'members' => ['name' => 'Member', 'position' => 'Director', 'kind' => 'manager'], 'services' => ['name' => 'Service', 'requirements' => ['Identity']],
            'links' => ['title' => 'Link', 'url' => 'https://example.org'], 'faqs' => ['question' => 'Question', 'answer' => 'Answer'], 'quotes' => ['author' => 'Author', 'text' => 'Quote'],
            'galleries' => ['title' => 'Gallery'], 'gallery-items' => ['gallery_id' => $gallery->id, 'media_id' => $media, 'title' => 'Caption'],
            'polls' => ['question' => 'Question'], 'poll-options' => ['poll_id' => $poll->id, 'title' => 'Option'],
            'forms' => ['title' => 'Form', 'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]]],
        ];
        foreach (['news', 'announcements', 'resolutions', 'pages', 'publications', 'tenders', 'contracts', 'calls', 'events', 'duties', 'documents'] as $resource) {
            $fixtures[$resource] = ['title' => 'Sample '.$resource, 'body' => '<p>Sample body</p>'];
        }
        foreach ($fixtures as $resource => $data) {
            $response = $this->postJson('/api/v1/admin/'.$resource, $data);
            $response->assertCreated();
            $id = $response->json('data.id');
            $this->assertNotNull($id, $resource);
            $this->getJson('/api/v1/admin/'.$resource.'/'.$id)->assertOk()->assertJsonPath('data.id', $id);
            $key = array_key_exists('title', $data) ? 'title' : (array_key_exists('name', $data) ? 'name' : (array_key_exists('question', $data) ? 'question' : 'text'));
            $this->patchJson('/api/v1/admin/'.$resource.'/'.$id, [$key => 'Updated '.$resource])->assertOk();
            $this->getJson('/api/v1/admin/'.$resource.'?per_page=2')->assertOk()->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);
        }
    }

    public function test_forum_moderation_keeps_pending_topics_and_replies_private(): void
    {
        $category = Category::create(['type' => 'forum', 'name' => 'Forum', 'slug' => 'forum']);
        Sanctum::actingAs($this->citizen());
        $topic = $this->postJson('/api/v1/forum/topics', ['category_id' => $category->id, 'title' => 'Question', 'body' => 'A longer sample question'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/forum/topics/'.$topic)->assertNotFound();
        Sanctum::actingAs($this->admin());
        $this->patchJson('/api/v1/admin/moderation/forum-topics/'.$topic, ['status' => 'approved'])->assertOk();
        Sanctum::actingAs($this->citizen());
        $reply = $this->postJson('/api/v1/forum/topics/'.$topic.'/replies', ['body' => 'Pending answer'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/forum/topics/'.$topic)->assertOk()->assertJsonCount(0, 'data.replies');
        Sanctum::actingAs($this->admin());
        $this->patchJson('/api/v1/admin/moderation/forum-replies/'.$reply, ['status' => 'approved'])->assertOk();
        $this->getJson('/api/v1/forum/topics/'.$topic)->assertJsonCount(1, 'data.replies');
    }

    public function test_local_password_recovery_changes_credentials_and_revokes_sessions(): void
    {
        $this->admin()->createToken('old');
        $token = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'admin@ostandari.test'])->assertOk()->json('data.dev_token');
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'admin@ostandari.test', 'token' => $token, 'password' => 'New-Strong-Password42!', 'password_confirmation' => 'New-Strong-Password42!'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'New-Strong-Password42!'])->assertOk();
    }

    public function test_profile_cannot_escalate_role_or_edit_verified_identifiers(): void
    {
        $user = $this->citizen();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/auth/profile', ['name' => 'New Name', 'role_id' => $this->admin()->role_id, 'mobile' => '09120000999', 'access_scope' => 'all'])->assertOk();
        $user->refresh();
        $this->assertSame('citizen', $user->role->name);
        $this->assertNull($user->mobile);
        $this->assertSame('organization', $user->access_scope);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_private_media_cannot_be_attached_to_public_content_or_another_citizens_report(): void
    {
        Storage::fake('local');
        $owner = $this->citizen();
        Sanctum::actingAs($owner);
        $id = $this->post('/api/v1/media', ['file' => UploadedFile::fake()->createWithContent('private.txt', 'private'), 'visibility' => 'private'], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/v1/admin/news', ['title' => 'No leak', 'body' => 'Text', 'cover_media_id' => $id])->assertUnprocessable();
        Sanctum::actingAs($this->citizen());
        $this->postJson('/api/v1/citizen/tickets', ['type' => 'governor', 'subject' => 'X', 'body' => 'Request with attachment', 'media_ids' => [$id]])->assertForbidden();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_failed_otp_delivery_cannot_leave_a_usable_code_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['portal.otp.driver' => 'log']);
        $this->postJson('/api/v1/auth/otp/request', ['name' => 'Test Citizen', 'mobile' => '09120000088', 'national_code' => '۱۲۳۴۵۶۷۸۹۱', 'terms_accepted' => true, 'terms_version' => \App\Services\RegistrationTerms::VERSION, 'terms_document_sha256' => \App\Services\RegistrationTerms::documentHash()])->assertStatus(503)->assertJsonMissingPath('data.dev_code');
        $this->assertNotNull(DB::table('otp_challenges')->first()->consumed_at);
    }

    public function test_poll_cannot_be_activated_without_options_or_rewritten_after_votes(): void
    {
        Sanctum::actingAs($this->admin());
        $poll = $this->postJson('/api/v1/admin/polls', ['question' => 'Q'])->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/polls/'.$poll, ['status' => 'active'])->assertUnprocessable();
        $a = $this->postJson('/api/v1/admin/poll-options', ['poll_id' => $poll, 'title' => 'A'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/poll-options', ['poll_id' => $poll, 'title' => 'B'])->assertCreated();
        $this->patchJson('/api/v1/admin/polls/'.$poll, ['status' => 'active'])->assertOk();
        Sanctum::actingAs($this->citizen());
        $this->postJson('/api/v1/polls/'.$poll.'/vote', ['option_id' => $a])->assertCreated();
        Sanctum::actingAs($this->admin());
        $this->patchJson('/api/v1/admin/polls/'.$poll, ['question' => 'Changed'])->assertConflict();
        $this->deleteJson('/api/v1/admin/poll-options/'.$a)->assertConflict();
    }

    public function test_malformed_filters_return_validation_errors_and_do_not_reach_sql(): void
    {
        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/admin/contents?organization_id[]=1')->assertUnprocessable();
        $this->getJson('/api/v1/services?category_id[]=1')->assertUnprocessable();
        $this->getJson('/api/v1/news?per_page=100000')->assertUnprocessable();
    }

    public function test_form_file_answers_must_belong_to_the_current_user(): void
    {
        Storage::fake('local');
        $first = $this->citizen();
        Sanctum::actingAs($first);
        $media = $this->post('/api/v1/media', ['visibility' => 'private', 'file' => UploadedFile::fake()->createWithContent('my.txt', 'file')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $form = DynamicForm::create(['title' => 'Upload', 'slug' => 'upload', 'is_active' => true, 'fields' => [['key' => 'file', 'label' => 'File', 'type' => 'file', 'required' => true]]]);
        Sanctum::actingAs($this->citizen());
        $this->postJson('/api/v1/citizen/forms/'.$form->id.'/submissions', ['answers' => ['file' => $media]])->assertUnprocessable();
        Sanctum::actingAs($first);
        $this->postJson('/api/v1/citizen/forms/'.$form->id.'/submissions', ['answers' => ['file' => $media]])->assertCreated();
    }

    public function test_working_calendar_skips_configured_friday_and_holidays(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'Asia/Tehran'));
        config(['portal.sla.weekend_days' => [5], 'portal.sla.holidays' => ['2026-10-17']]);
        $this->assertSame('2026-10-18',PortalCalendar::deadline(1)->toDateString());
        $this->travelBack();
    }
}
