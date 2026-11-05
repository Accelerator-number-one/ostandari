<?php

namespace Tests\Feature;

use App\Models\{Content, Media, Role, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class ContentPresentationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function article(string $type = 'news'): Content
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        return Content::create(['type' => $type, 'title' => 'مطلب آزمایشی '.$type,
            'slug' => 'presentation-'.$type, 'body' => '<p>متن کامل مطلب</p><p>&nbsp;</p><p><br></p><img src="/storage/test/photo.webp" alt="تصویر متن">',
            'locale' => 'fa', 'organization_id' => $admin->organization_id, 'author_id' => $admin->id,
            'comments_enabled' => true, 'status' => 'published', 'published_at' => now()->subMinute()]);
    }

    public function test_attachment_footer_is_removed_and_inline_images_and_media_records_remain(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $media = Media::create(['user_id' => $admin->id, 'organization_id' => $admin->organization_id,
            'disk' => 'public', 'path' => 'test/photo.webp', 'original_name' => 'photo.webp',
            'mime' => 'image/webp', 'visibility' => 'public', 'size' => 123]);
        foreach (['news', 'announcement', 'resolution'] as $type) {
            $article = $this->article($type);
            $article->media()->attach($media->id, ['purpose' => 'attachment']);
            $this->get('/content/'.$article->id)->assertOk()->assertSee('متن کامل مطلب')
                ->assertSee('/storage/test/photo.webp', false)->assertDontSee('پیوست‌ها')
                ->assertDontSee('<p>&nbsp;</p>', false)->assertDontSee('<p><br /></p>', false)
                ->assertSee('کپی لینک')->assertSee('اشتراک‌گذاری');
            $this->assertTrue($article->fresh()->media()->whereKey($media->id)->exists());
        }
    }

    public function test_only_an_active_authenticated_citizen_can_post_or_expand_the_comment_form(): void
    {
        $article = $this->article();
        $endpoint = '/web-api/v1/contents/'.$article->id.'/comments';
        $this->postJson($endpoint, ['body' => 'دیدگاه مهمان'])->assertUnauthorized();
        $this->actingAs(User::where('username', 'admin')->firstOrFail(), 'web')
            ->postJson($endpoint, ['body' => 'دیدگاه مدیر'])->assertForbidden();
        $this->get('/content/'.$article->id)->assertDontSee('portal-comment-compose', false);
        $citizen = User::create(['name' => 'شهروند آزمایشی', 'person_type' => 'citizen', 'is_active' => true,
            'role_id' => Role::where('name', 'citizen')->value('id')]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($citizen, 'web')->postJson($endpoint, ['body' => 'دیدگاه معتبر شهروند'])->assertCreated();
        $this->get('/content/'.$article->id)->assertOk()->assertSee('<details class="portal-comment-compose">', false);
        $this->assertDatabaseHas('comments', ['content_id' => $article->id, 'user_id' => $citizen->id, 'status' => 'pending']);
        $citizen->update(['is_active' => false]);
        $this->postJson($endpoint, ['body' => 'دیدگاه حساب غیرفعال'])->assertForbidden();
    }
}
