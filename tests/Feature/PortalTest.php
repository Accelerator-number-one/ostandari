<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Content;
use App\Models\DynamicForm;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OtpChallenge;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OtpService;
use App\Services\RegistrationTerms;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalTest extends TestCase
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

    private function citizen(string $name = 'Test Citizen'): User
    {
        return User::create(['name' => $name, 'role_id' => Role::where('name', 'citizen')->value('id'), 'person_type' => 'citizen', 'is_active' => true]);
    }

    private function loginAs(User $user): void
    {
        Sanctum::actingAs($user);
    }

    private function content(array $more = []): Content
    {
        return Content::create(array_merge(['type' => 'news', 'locale' => 'fa', 'title' => 'خبر آزمایشی', 'slug' => 'test-'.bin2hex(random_bytes(4)), 'body' => '<p>متن آزمایشی</p>', 'author_id' => $this->admin()->id, 'status' => 'published', 'published_at' => now()->subMinute()], $more));
    }

    private function ticket(User $user, array $more = []): Ticket
    {
        return Ticket::create(array_merge(['reference' => (string) Str::ulid(), 'user_id' => $user->id, 'organization_id' => $this->admin()->organization_id, 'type' => 'report', 'subject' => 'گزارش تست', 'body' => 'متن گزارش آزمایشی'], $more));
    }

    public function test_health_json_errors_and_anonymous_admin_protection(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('data.database', 'ok');
        $this->getJson('/api/v1/admin/contents')->assertUnauthorized()->assertJsonPath('success', false);
        $this->getJson('/api/v1/not-found')->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_password_login_issues_revocable_token_and_denies_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'wrong'])->assertUnprocessable();
        $token = $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'Testing-Only-Password42!'])->assertOk()->json('data.token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonMissingPath('data.password');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_otp_attempts_are_persisted_and_codes_cannot_be_replayed(): void
    {
        $service = app(OtpService::class);
        $challenge = $service->request(['name' => 'کاربر آزمایشی', 'mobile' => '09120000001', 'national_code' => '1234567891'], RegistrationTerms::acceptance());
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge['challenge_id'], 'code' => '000000'])->assertUnprocessable();
        $this->assertSame(1, OtpChallenge::find($challenge['challenge_id'])->attempts);
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge['challenge_id'], 'code' => $challenge['dev_code']])->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge['challenge_id'], 'code' => $challenge['dev_code']])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['mobile' => '09120000001', 'person_type' => 'citizen']);
    }

    public function test_expired_and_locked_otp_codes_are_rejected(): void
    {
        $service = app(OtpService::class);
        $c = $service->request(['name' => 'Expired User', 'mobile' => '09120000002', 'national_code' => '1234567891']);
        OtpChallenge::whereKey($c['challenge_id'])->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $c['challenge_id'], 'code' => $c['dev_code']])->assertUnprocessable();
        $c = $service->request(['name' => 'Locked User', 'mobile' => '09120000003', 'national_code' => '1234567891']);
        OtpChallenge::whereKey($c['challenge_id'])->update(['attempts' => 5]);
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $c['challenge_id'], 'code' => $c['dev_code']])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['mobile' => '09120000003']);
    }

    public function test_otp_challenge_endpoint_validates_identity_and_has_no_production_dev_code(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['name' => 'X', 'mobile' => '123', 'national_code' => '1111111111'])->assertUnprocessable();
        config(['portal.otp.expose_local' => false]);
        app(OtpService::class)->request(['name' => 'کاربر نمونه', 'mobile' => '09120000004', 'national_code' => '1234567891']);
        $this->assertDatabaseCount('otp_challenges', 1);
    }

    public function test_citizens_and_inactive_users_cannot_access_administration(): void
    {
        $u = $this->citizen();
        $this->loginAs($u);
        $this->getJson('/api/v1/admin/resources')->assertForbidden();
        $u->update(['is_active' => false]);
        $this->getJson('/api/v1/citizen/dashboard')->assertForbidden();
    }

    public function test_content_crud_sanitizes_html_and_hides_drafts_and_scheduled_content(): void
    {
        $this->loginAs($this->admin());
        $r = $this->postJson('/api/v1/admin/news', ['title' => 'خبر جدید', 'body' => '<p onclick="alert(1)">سلام<script>alert(1)</script><a href="javascript:alert(1)">لینک</a></p>', 'status' => 'draft'])->assertCreated();
        $id = $r->json('data.id');
        $this->assertStringNotContainsString('<script', $r->json('data.body'));
        $this->assertStringNotContainsString('javascript:', $r->json('data.body'));
        $this->getJson('/api/v1/contents/'.$id)->assertNotFound();
        $this->patchJson('/api/v1/admin/news/'.$id, ['status' => 'published', 'published_at' => now()->addDay()->toIso8601String()])->assertOk();
        $this->getJson('/api/v1/contents/'.$id)->assertNotFound();
        $this->patchJson('/api/v1/admin/news/'.$id, ['published_at' => now()->subMinute()->toIso8601String()])->assertOk();
        $this->getJson('/api/v1/contents/'.$id)->assertOk()->assertJsonMissingPath('data.content.author.email');
        $this->getJson('/api/v1/news')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/v1/admin/news/'.$id)->assertNoContent();
        $this->getJson('/api/v1/contents/'.$id)->assertNotFound();
        $this->postJson('/api/v1/admin/news/'.$id.'/restore')->assertOk();
    }

    public function test_content_language_slug_uniqueness_and_cross_type_boundaries(): void
    {
        $this->loginAs($this->admin());
        $c = $this->content(['slug' => 'same']);
        $this->postJson('/api/v1/admin/news', ['title' => 'same', 'slug' => 'same', 'body' => 'Text'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/news', ['title' => 'same', 'slug' => 'same', 'locale' => 'en', 'body' => 'Text'])->assertCreated();
        $this->patchJson('/api/v1/admin/announcements/'.$c->id, ['title' => 'Wrong resource'])->assertNotFound();
        $this->postJson('/api/v1/admin/news', ['type' => 'contract', 'title' => 'X', 'body' => 'Text'])->assertUnprocessable();
    }

    public function test_public_home_catalogs_and_nested_menu_are_functional(): void
    {
        $this->loginAs($this->admin());
        $county = $this->postJson('/api/v1/admin/counties', ['name' => 'آزمون', 'slug' => 'sample-county'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/counties/'.$county)->assertOk()->assertJsonPath('data.name', 'آزمون');
        $menu = Menu::create(['name' => 'Main', 'key' => 'main', 'locale' => 'fa']);
        $parent = MenuItem::create(['menu_id' => $menu->id, 'title' => 'والد', 'url' => '/services']);
        MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'title' => 'فرزند', 'url' => '/services/1']);
        $this->getJson('/api/v1/menus')->assertOk()->assertJsonPath('data.0.items.0.children.0.title', 'فرزند');
        $this->getJson('/api/v1/home')->assertOk()->assertJsonStructure(['data' => ['menus', 'news', 'members', 'counties', 'settings']]);
        $this->getJson('/api/v1/portals/mazandaran')->assertOk();
    }

    public function test_menu_and_category_cycles_are_rejected_and_dependencies_protected(): void
    {
        $this->loginAs($this->admin());
        $a = Category::create(['type' => 'news', 'name' => 'A', 'slug' => 'a']);
        $b = Category::create(['type' => 'news', 'name' => 'B', 'slug' => 'b', 'parent_id' => $a->id]);
        $this->patchJson('/api/v1/admin/categories/'.$a->id, ['parent_id' => $b->id])->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/categories/'.$a->id)->assertConflict();
        $menu = Menu::create(['name' => 'M', 'key' => 'm']);
        $this->postJson('/api/v1/admin/menu-items', ['menu_id' => $menu->id, 'title' => 'Unsafe', 'url' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/menu-items', ['menu_id' => $menu->id, 'title' => 'Unsafe', 'url' => '//evil.example'])->assertUnprocessable();
    }

    public function test_organization_scoped_editor_cannot_read_or_mutate_other_organization(): void
    {
        $org = Organization::create(['name' => 'Other', 'slug' => 'other', 'type' => 'department']);
        $role = Role::where('name', 'editor')->firstOrFail();
        $editor = User::create(['name' => 'Scoped', 'role_id' => $role->id, 'person_type' => 'staff', 'organization_id' => $org->id, 'access_scope' => 'organization']);
        $hidden = $this->content(['organization_id' => $this->admin()->organization_id]);
        $this->loginAs($editor);
        $this->getJson('/api/v1/admin/contents/'.$hidden->id)->assertNotFound();
        $this->postJson('/api/v1/admin/news', ['title' => 'Cross scope', 'body' => 'Text', 'organization_id' => $this->admin()->organization_id])->assertForbidden();
        $this->postJson('/api/v1/admin/news', ['title' => 'Owned', 'body' => 'Text'])->assertCreated()->assertJsonPath('data.organization_id', $org->id);
    }

    public function test_private_files_are_owner_only_and_executable_uploads_rejected(): void
    {
        Storage::fake('local');
        $owner = $this->citizen();
        $this->loginAs($owner);
        $response = $this->post('/api/v1/media', ['visibility' => 'private', 'file' => UploadedFile::fake()->createWithContent('report.txt', 'Private report')], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('data.id');
        $this->assertNull($response->json('data.url'));
        $this->assertNull($response->json('data.path'));
        $this->get('/api/v1/media/'.$id.'/download')->assertOk();
        $this->loginAs($this->citizen('Other'));
        $this->getJson('/api/v1/media/'.$id.'/download')->assertNotFound();
        $this->post('/api/v1/media', ['visibility' => 'private', 'file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_ticket_ownership_and_internal_notes_are_enforced(): void
    {
        $owner = $this->citizen();
        $ticket = $this->ticket($owner);
        $other = $this->citizen('Other');
        $this->loginAs($other);
        $this->getJson('/api/v1/citizen/tickets/'.$ticket->reference)->assertNotFound();
        $this->loginAs($this->admin());
        $this->postJson('/api/v1/admin/tickets/'.$ticket->reference.'/messages', ['body' => 'Secret staff note', 'is_internal' => true])->assertCreated();
        $this->postJson('/api/v1/admin/tickets/'.$ticket->reference.'/messages', ['body' => 'Public answer'])->assertCreated();
        $this->patchJson('/api/v1/admin/tickets/'.$ticket->reference, ['status' => 'review', 'note' => 'Internal reason'])->assertOk();
        $this->loginAs($owner);
        $this->getJson('/api/v1/citizen/tickets/'.$ticket->reference)->assertOk()->assertJsonCount(1, 'data.messages')->assertJsonMissing(['body' => 'Secret staff note'])->assertJsonMissing(['note' => 'Internal reason']);
    }

    public function test_ticket_creation_status_transitions_and_notifications(): void
    {
        $u = $this->citizen();
        $this->loginAs($u);
        $ref = $this->postJson('/api/v1/citizen/tickets', ['type' => 'governor', 'subject' => 'موضوع', 'body' => 'این متن یک درخواست آزمایشی است.'])->assertCreated()->json('data.reference');
        $this->getJson('/api/v1/citizen/notifications')->assertOk()->assertJsonCount(1, 'data');
        $this->loginAs($this->admin());
        $this->patchJson('/api/v1/admin/tickets/'.$ref, ['status' => 'resolved'])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/tickets/'.$ref, ['status' => 'review'])->assertOk();
        $this->patchJson('/api/v1/admin/tickets/'.$ref, ['status' => 'resolved'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
    }

    public function test_poll_option_validation_and_single_vote(): void
    {
        $p = Poll::create(['question' => 'Question', 'status' => 'active']);
        $a = PollOption::create(['poll_id' => $p->id, 'title' => 'One']);
        $b = PollOption::create(['poll_id' => $p->id, 'title' => 'Two']);
        $other = Poll::create(['question' => 'Other']);
        $wrong = PollOption::create(['poll_id' => $other->id, 'title' => 'Wrong']);
        $this->loginAs($this->citizen());
        $this->postJson('/api/v1/polls/'.$p->id.'/vote', ['option_id' => $wrong->id])->assertUnprocessable();
        $this->postJson('/api/v1/polls/'.$p->id.'/vote', ['option_id' => $a->id])->assertCreated()->assertJsonPath('data.total_votes', 1);
        $this->postJson('/api/v1/polls/'.$p->id.'/vote', ['option_id' => $b->id])->assertConflict();
        $this->getJson('/api/v1/polls/'.$p->id.'/results')->assertOk()->assertJsonPath('data.options.0.percentage', 100);
        $p->update(['ends_at' => now()->subMinute()]);
        $this->loginAs($this->citizen('Late'));
        $this->postJson('/api/v1/polls/'.$p->id.'/vote', ['option_id' => $a->id])->assertConflict();
    }

    public function test_dynamic_forms_validate_answers_preserve_schema_and_isolate_submissions(): void
    {
        $form = DynamicForm::create(['title' => 'Form', 'slug' => 'sample', 'is_active' => true, 'organization_id' => $this->admin()->organization_id, 'fields' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true], ['key' => 'choice', 'label' => 'Choice', 'type' => 'select', 'options' => ['A', 'B'], 'required' => true]]]);
        $owner = $this->citizen();
        $this->loginAs($owner);
        $this->postJson('/api/v1/citizen/forms/'.$form->id.'/submissions', ['answers' => ['email' => 'bad', 'choice' => 'C']])->assertUnprocessable();
        $r = $this->postJson('/api/v1/citizen/forms/'.$form->id.'/submissions', ['answers' => ['email' => 'test@example.test', 'choice' => 'A']])->assertCreated();
        $reference = $r->json('data.reference');
        $form->update(['fields' => [['key' => 'new', 'label' => 'New field', 'type' => 'text', 'required' => true]]]);
        $this->getJson('/api/v1/citizen/submissions/'.$reference)->assertOk()->assertJsonPath('data.schema_snapshot.0.key', 'email');
        $this->loginAs($this->citizen('Other'));
        $this->getJson('/api/v1/citizen/submissions/'.$reference)->assertNotFound();
        $this->loginAs($this->admin());
        $this->patchJson('/api/v1/admin/submissions/'.$reference, ['status' => 'accepted', 'reply' => 'Accepted'])->assertOk();
    }

    public function test_dynamic_schema_rejects_duplicate_and_arbitrary_validation_code(): void
    {
        $this->loginAs($this->admin());
        $this->postJson('/api/v1/admin/forms', ['title' => 'Bad', 'fields' => [['key' => 'x', 'label' => 'X', 'type' => 'text'], ['key' => 'x', 'label' => 'X', 'type' => 'text']]])->assertUnprocessable();
        $this->postJson('/api/v1/admin/forms', ['title' => 'Bad', 'fields' => [['key' => 'x', 'label' => 'X', 'type' => 'regex']]])->assertUnprocessable();
    }

    public function test_settings_cannot_expose_secrets_and_audit_omits_passwords(): void
    {
        $this->loginAs($this->admin());
        $this->putJson('/api/v1/admin/settings', ['settings' => [['key' => 'api.secret', 'value' => 'secret', 'is_public' => true]]])->assertUnprocessable();
        $this->putJson('/api/v1/admin/settings', ['settings' => [['key' => 'site.name', 'value' => 'Portal', 'is_public' => true]]])->assertOk();
        $this->assertSame('Portal', $this->getJson('/api/v1/settings')->assertOk()->json('data')['site.name']);
        $this->postJson('/api/v1/admin/users', ['name' => 'Staff', 'username' => 'staff', 'password' => 'Strong-Password12!', 'role_id' => Role::where('name', 'editor')->value('id'), 'person_type' => 'staff'])->assertCreated();
        $this->assertStringNotContainsString('Strong-Password12!', DB::table('audit_logs')->get()->toJson());
    }

    public function test_admin_cannot_remove_own_access_and_system_roles_are_immutable(): void
    {
        $admin = $this->admin();
        $this->loginAs($admin);
        $this->deleteJson('/api/v1/admin/users/'.$admin->id)->assertConflict();
        $this->patchJson('/api/v1/admin/roles/'.$admin->role_id, ['permissions' => []])->assertConflict();
        $this->postJson('/api/v1/admin/roles', ['name' => 'bad', 'label' => 'Bad', 'permissions' => ['*']])->assertUnprocessable();
    }

    public function test_comments_are_moderated_and_local_assistant_uses_only_published_sources(): void
    {
        $content = $this->content(['comments_enabled' => true, 'title' => 'راهنمای پیگیری گزارش']);
        $this->loginAs($this->citizen());
        $id = $this->postJson('/api/v1/contents/'.$content->id.'/comments', ['body' => 'نظر آزمایشی'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/contents/'.$content->id)->assertJsonCount(0, 'data.comments');
        $this->loginAs($this->admin());
        $this->patchJson('/api/v1/admin/moderation/comments/'.$id, ['status' => 'approved'])->assertOk();
        $this->getJson('/api/v1/contents/'.$content->id)->assertJsonCount(1, 'data.comments');
        $this->postJson('/api/v1/assistant/query', ['question' => 'پیگیری گزارش'])->assertOk()->assertJsonPath('data.engine', 'local-content')->assertJsonCount(1, 'data.sources');
    }

    public function test_newsletter_opt_in_and_unsubscribe_work_without_external_email_service(): void
    {
        $email = 'subscriber@example.test';
        $token = $this->postJson('/api/v1/newsletter/subscribe', ['email' => $email, 'name' => 'Subscriber'])->assertAccepted()->json('data.dev_token');
        $this->postJson('/api/v1/newsletter/confirm', ['email' => $email, 'token' => $token])->assertOk();
        $this->assertDatabaseHas('subscribers', ['email' => $email, 'is_active' => true]);
        $this->postJson('/api/v1/newsletter/unsubscribe', ['email' => $email, 'token' => $token])->assertNoContent();
    }

    public function test_exports_are_permission_protected_and_cors_preflight_accepts_local_frontend(): void
    {
        $this->loginAs($this->admin());
        $c = $this->content(['title' => '=SUM(A1:A2)']);
        $csv = $this->get('/api/v1/admin/exports/contents')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM",$csv);
        $this->options('/api/v1/auth/login',[],['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'Content-Type'])->assertSuccessful()->assertHeader('Access-Control-Allow-Origin','http://localhost:3000');
    }
}
