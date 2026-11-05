<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorReadsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($this->admin);
    }

    public function test_role_and_subscriber_can_be_opened_directly_without_exposing_subscription_tokens(): void
    {
        $role = Role::create(['name' => 'fixture_editor_read', 'label' => 'Fixture editor', 'permissions' => ['content.manage']]);
        $this->getJson('/api/v1/admin/roles/'.$role->id)->assertOk()->assertJsonPath('data.name', 'fixture_editor_read');
        $subscriber = Subscriber::create(['name' => 'Fixture subscriber', 'email' => 'fixture-editor@example.test', 'token_hash' => str_repeat('a', 64), 'verified_at' => now(), 'is_active' => true]);
        $this->getJson('/api/v1/admin/subscribers/'.$subscriber->id)->assertOk()->assertJsonPath('data.email', $subscriber->email)->assertJsonMissingPath('data.token_hash');
        $this->getJson('/api/v1/admin/roles/99999999')->assertNotFound();
        $this->getJson('/api/v1/admin/subscribers/99999999')->assertNotFound();
    }

    public function test_all_three_moderation_editors_read_the_requested_record(): void
    {
        $content = Content::create(['author_id' => $this->admin->id, 'slug' => 'fixture-article-editor-read', 'type' => 'news', 'title' => 'Fixture article', 'body' => 'Fixture article body', 'organization_id' => $this->admin->organization_id]);
        $comment = Comment::create(['content_id' => $content->id, 'user_id' => $this->admin->id, 'body' => 'Fixture comment', 'status' => 'pending']);
        $category = Category::create(['slug' => 'fixture-forum-editor-read', 'type' => 'forum', 'name' => 'Fixture forum', 'organization_id' => $this->admin->organization_id]);
        $topic = ForumTopic::create(['category_id' => $category->id, 'user_id' => $this->admin->id, 'title' => 'Fixture topic', 'body' => 'Fixture topic body', 'status' => 'pending']);
        $reply = ForumReply::create(['forum_topic_id' => $topic->id, 'user_id' => $this->admin->id, 'body' => 'Fixture reply', 'status' => 'pending']);
        foreach (['comments' => $comment, 'forum-topics' => $topic, 'forum-replies' => $reply] as $kind => $record) {
            $this->getJson('/api/v1/admin/moderation/'.$kind.'/'.$record->id)->assertOk()->assertJsonPath('data.id', $record->id)->assertJsonPath('data.body', $record->body)->assertJsonPath('data.author.id', $this->admin->id);
            $this->getJson('/api/v1/admin/moderation/'.$kind.'/99999999')->assertNotFound();
        }
        $this->getJson('/api/v1/admin/moderation/not-a-kind/1')->assertNotFound();
    }

    public function test_new_read_routes_require_the_matching_permission(): void
    {
        $role = Role::create(['name' => 'fixture_no_editor_permissions', 'label' => 'No editing', 'permissions' => []]);
        $user = User::create(['name' => 'Fixture moderator', 'role_id' => $role->id, 'person_type' => 'staff', 'access_scope' => 'all']);
        Sanctum::actingAs($user);
        foreach (['roles/1', 'subscribers/1', 'moderation/comments/1', 'moderation/forum-topics/1', 'moderation/forum-replies/1'] as $path) {
            $this->getJson('/api/v1/admin/'.$path)->assertForbidden();
        }
    }

    public function test_moderation_detail_reads_keep_the_same_organization_scope_as_the_list(): void
    {
        $allowed = Organization::create(['name' => 'Allowed editor organization', 'slug' => 'allowed-editor-org', 'type' => 'department']);
        $other = Organization::create(['name' => 'Other editor organization', 'slug' => 'other-editor-org', 'type' => 'department']);
        $role = Role::create(['name' => 'fixture_scoped_moderator', 'label' => 'Scoped moderator', 'permissions' => ['comments.manage', 'forum.manage', 'newsletter.manage']]);
        $user = User::create(['name' => 'Fixture moderator', 'role_id' => $role->id, 'person_type' => 'staff', 'organization_id' => $allowed->id, 'access_scope' => 'organization']);
        foreach ([$allowed, $other] as $organization) {
            $content = Content::create(['author_id' => $this->admin->id, 'slug' => 'scoped-article-'.$organization->id, 'type' => 'news', 'title' => 'Scoped article', 'body' => 'Scoped article body', 'organization_id' => $organization->id]);
            $comment = Comment::create(['content_id' => $content->id, 'user_id' => $this->admin->id, 'body' => 'Scoped comment', 'status' => 'pending']);
            $category = Category::create(['slug' => 'scoped-forum-'.$organization->id, 'type' => 'forum', 'name' => 'Scoped forum', 'organization_id' => $organization->id]);
            $topic = ForumTopic::create(['category_id' => $category->id, 'user_id' => $this->admin->id, 'title' => 'Scoped topic', 'body' => 'Scoped topic body', 'status' => 'pending']);
            $reply = ForumReply::create(['forum_topic_id' => $topic->id, 'user_id' => $this->admin->id, 'body' => 'Scoped reply', 'status' => 'pending']);
            Sanctum::actingAs($user);
            foreach (['comments' => $comment, 'forum-topics' => $topic, 'forum-replies' => $reply] as $kind => $record) {
                $this->getJson('/api/v1/admin/moderation/'.$kind.'/'.$record->id)->assertStatus($organization->id === $allowed->id ? 200 : 404);
            }
        }
        $this->getJson('/api/v1/admin/subscribers/1')->assertForbidden();
    }
}
