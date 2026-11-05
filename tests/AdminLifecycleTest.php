<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Content;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\ForumTopic;
use App\Models\Menu;
use App\Models\Poll;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\ResourceRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        Storage::fake('public');
    }

    private function image(): int
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');

        return $this->post('/api/v1/media', [
            'visibility' => 'public',
            'file' => UploadedFile::fake()->createWithContent('fixture.png', $png),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    public function test_resource_records_can_be_deleted_and_soft_deleted_records_restored(): void
    {
        $image = $this->image();
        $menu = Menu::create(['name' => 'Fixture menu', 'key' => 'fixture-menu']);
        $gallery = Gallery::create(['title' => 'Fixture gallery', 'slug' => 'fixture-gallery']);
        $poll = Poll::create(['question' => 'Fixture poll']);
        $fixtures = [
            'contents' => ['type' => 'news', 'title' => 'Content', 'body' => 'Text'],
            'categories' => ['name' => 'Category', 'type' => 'news'],
            'tags' => ['name' => 'Tag'], 'counties' => ['name' => 'County'],
            'organizations' => ['name' => 'Organization', 'type' => 'department'],
            'menus' => ['name' => 'Footer', 'key' => 'fixture-footer'],
            'menu-items' => ['menu_id' => $menu->id, 'title' => 'Item', 'url' => '/news'],
            'slides' => ['title' => 'Slide', 'media_id' => $image],
            'advertisements' => ['title' => 'Advertisement', 'media_id' => $image],
            'members' => ['name' => 'Member', 'position' => 'Director', 'kind' => 'manager'],
            'services' => ['name' => 'Service'],
            'links' => ['title' => 'Link', 'url' => 'https://example.test'],
            'faqs' => ['question' => 'Question', 'answer' => 'Answer'],
            'quotes' => ['author' => 'Author', 'text' => 'Quote'],
            'galleries' => ['title' => 'Gallery'],
            'gallery-items' => ['gallery_id' => $gallery->id, 'media_id' => $image],
            'polls' => ['question' => 'Question'],
            'poll-options' => ['poll_id' => $poll->id, 'title' => 'Option'],
            'forms' => ['title' => 'Form', 'fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'text']]],
        ];
        foreach (['news', 'announcements', 'resolutions', 'pages', 'publications', 'tenders', 'contracts', 'calls', 'events', 'duties', 'documents'] as $resource) {
            $fixtures[$resource] = ['title' => 'Fixture '.$resource, 'body' => 'Fixture body'];
        }
        foreach ($fixtures as $resource => $payload) {
            $endpoint = '/api/v1/admin/'.$resource;
            $id = $this->postJson($endpoint, $payload)->assertCreated()->json('data.id');
            $this->deleteJson($endpoint.'/'.$id)->assertNoContent();
            $this->getJson($endpoint.'/'.$id)->assertNotFound();
            $class = ResourceRegistry::get($resource)['model'];
            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $this->postJson($endpoint.'/'.$id.'/restore')->assertOk()->assertJsonPath('data.id', $id);
                $this->getJson($endpoint.'/'.$id)->assertOk();
            } else {
                $this->postJson($endpoint.'/'.$id.'/restore')->assertStatus(405);
            }
        }
    }

    public function test_role_and_staff_types_follow_the_roles_response_and_user_deactivation_contract(): void
    {
        $role = $this->postJson('/api/v1/admin/roles', [
            'name' => 'fixture_editor', 'label' => 'Fixture editor', 'permissions' => ['content.manage'],
        ])->assertCreated()->json('data.id');
        $response = $this->getJson('/api/v1/admin/roles')->assertOk()->assertJsonStructure(['data' => ['permissions', 'roles']]);
        $this->assertContains($role, array_column($response->json('data.roles'), 'id'));
        $this->patchJson('/api/v1/admin/roles/'.$role, ['label' => 'Edited role'])->assertOk()->assertJsonPath('data.label', 'Edited role');
        foreach (['staff', 'employee', 'expert'] as $type) {
            $id = $this->postJson('/api/v1/admin/users', [
                'name' => 'Fixture '.$type, 'username' => 'fixture_'.$type,
                'password' => 'Fixture-Only-Password42!', 'role_id' => $role,
                'person_type' => $type, 'access_scope' => 'organization', 'is_active' => true,
            ])->assertCreated()->json('data.id');
            $this->getJson('/api/v1/admin/users?person_type='.$type)->assertOk();
            $this->patchJson('/api/v1/admin/users/'.$id, ['name' => 'Edited '.$type])->assertOk()->assertJsonPath('data.person_type', $type);
            $this->deleteJson('/api/v1/admin/users/'.$id)->assertNoContent();
            $this->assertDatabaseHas('users', ['id' => $id, 'is_active' => false]);
        }
        $this->deleteJson('/api/v1/admin/roles/'.$role)->assertConflict();
        $unused = $this->postJson('/api/v1/admin/roles', ['name' => 'fixture_unused', 'label' => 'Unused', 'permissions' => ['content.manage']])->assertCreated()->json('data.id');
        $this->deleteJson('/api/v1/admin/roles/'.$unused)->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $unused]);
    }

    public function test_published_content_and_settings_reach_public_reads_and_attached_images_remain_protected(): void
    {
        $image = $this->image();
        $id = $this->postJson('/api/v1/admin/news', [
            'title' => 'Fixture public news', 'body' => '<p>Fixture body</p>',
            'cover_media_id' => $image, 'status' => 'draft',
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/contents/'.$id)->assertNotFound();
        $this->patchJson('/api/v1/admin/news/'.$id, ['status' => 'published'])->assertOk();
        $this->getJson('/api/v1/contents/'.$id)->assertOk()->assertJsonPath('data.content.cover.id', $image);
        $home = $this->getJson('/api/v1/home')->assertOk();
        $this->assertContains($id, array_column($home->json('data.news'), 'id'));
        $this->get('/api/v1/media/'.$image.'/download')->assertOk();
        $this->deleteJson('/api/v1/media/'.$image)->assertConflict();
        $this->putJson('/api/v1/admin/settings', ['settings' => [
            ['key' => 'site.name', 'value' => 'Fixture portal', 'is_public' => true],
        ]])->assertOk();
        $this->assertSame('Fixture portal', $this->getJson('/api/v1/settings')->assertOk()->json('data')['site.name']);
    }

    public function test_admin_search_matches_form_references_comments_topics_and_subscribers(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $form = DynamicForm::create(['title' => 'MatchForm', 'slug' => 'match-form', 'fields' => []]);
        FormSubmission::create(['dynamic_form_id' => $form->id, 'user_id' => $admin->id, 'reference' => 'MATCH_REFERENCE', 'data' => [], 'schema_snapshot' => []]);
        $this->getJson('/api/v1/admin/submissions?q=MATCH_REFERENCE')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', 'MATCH_REFERENCE');
        $this->getJson('/api/v1/admin/submissions?q=MatchForm')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/submissions?q=MATCH_REFERENCE&status=rejected')->assertOk()->assertJsonCount(0, 'data');
        $content = Content::create(['type' => 'news', 'title' => 'Fixture', 'slug' => 'fixture', 'body' => 'Body', 'author_id' => $admin->id]);
        $comment = Comment::create(['content_id' => $content->id, 'user_id' => $admin->id, 'body' => 'MATCH_COMMENT']);
        Comment::create(['content_id' => $content->id, 'user_id' => $admin->id, 'body' => 'Other body']);
        $this->getJson('/api/v1/admin/moderation/comments?q=MATCH_COMMENT')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $comment->id);
        $this->getJson('/api/v1/admin/moderation/comments?q=MATCH_COMMENT&status=approved')->assertOk()->assertJsonCount(0, 'data');
        $category = Category::create(['name' => 'Forum', 'slug' => 'forum-fixture', 'type' => 'forum']);
        $topic = ForumTopic::create(['category_id' => $category->id, 'user_id' => $admin->id, 'title' => 'MATCH_TOPIC', 'body' => 'Neutral body']);
        $this->getJson('/api/v1/admin/moderation/forum-topics?q=MATCH_TOPIC')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $topic->id);
        $subscriber = Subscriber::create(['name' => 'MATCH_NAME', 'email' => 'match@example.test', 'token_hash' => hash('sha256', 'match-fixture')]);
        Subscriber::create(['name' => 'Other', 'email' => 'other@example.test', 'token_hash' => hash('sha256', 'other-fixture')]);
        $this->getJson('/api/v1/admin/subscribers?q=match@example.test')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $subscriber->id);
        $this->getJson('/api/v1/admin/subscribers?q=MATCH_NAME')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_search_rejects_array_input_before_querying(): void
    {
        foreach (['submissions', 'moderation/comments', 'moderation/forum-topics', 'subscribers'] as $resource) {
            $this->getJson('/api/v1/admin/'.$resource.'?q[]=invalid')->assertUnprocessable();
        }
    }
}
