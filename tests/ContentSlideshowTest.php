<?php

namespace Tests\Feature;

use App\Models\{Content, Media, Organization, Role, Slide, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ContentSlideshowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Media $image;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $org = Organization::where('type', 'county')->whereHas('county', fn ($q) => $q->where('slug', 'amol'))->firstOrFail();
        $this->actor = User::create(['name' => 'County PR', 'username' => 'slideshow_fixture', 'password' => 'Fixture-Password42!', 'person_type' => 'staff', 'organization_id' => $org->id, 'role_id' => Role::where('name', 'county_public_relations')->value('id'), 'access_scope' => 'organization', 'is_active' => true]);
        $this->image = Media::create(['organization_id' => $org->id, 'user_id' => $this->actor->id, 'disk' => 'public', 'path' => 'fixture/slide.webp', 'original_name' => 'slide.webp', 'mime' => 'image/webp', 'size' => 12, 'visibility' => 'public']);
        Sanctum::actingAs($this->actor);
    }

    private function create(array $extra = [])
    {
        return $this->postJson('/api/v1/admin/contents', $extra + ['type' => 'news', 'title' => 'خبر شهرستان', 'body' => '<p>متن کامل خبر</p>', 'cover_media_id' => $this->image->id, 'status' => 'published', 'show_in_slideshow' => true])->assertCreated();
    }

    private function slides(): array
    {
        return $this->getJson('/api/v1/portals/'.$this->actor->organization->slug)->assertOk()->json('data.slides');
    }

    public function test_all_content_types_can_be_promoted_with_their_own_detail_links(): void
    {
        foreach (config('portal.content_types') as $type) {
            $id = $this->create(['type' => $type, 'title' => $type])->assertJsonPath('data.show_in_slideshow', true)->json('data.id');
            $slide = collect($this->slides())->firstWhere('content_id', $id);
            $this->assertSame('/content/'.$id, $slide['url']);
            $this->assertSame($this->image->id, $slide['image']['id']);
            $this->getJson('/api/v1/contents/'.$id)->assertOk()->assertJsonPath('data.content.title', $type);
        }
        $this->assertSame(0, Slide::count(), 'Promotion must not create duplicate managed Slide rows.');
    }

    public function test_edit_uncheck_archive_delete_and_restore_follow_the_same_content(): void
    {
        $id = $this->create()->json('data.id');
        $this->patchJson('/api/v1/admin/contents/'.$id, ['title' => 'عنوان تازه'])->assertOk();
        $this->assertCount(1, $this->slides());
        $this->assertSame('عنوان تازه', $this->slides()[0]['title']);
        $this->patchJson('/api/v1/admin/contents/'.$id, ['show_in_slideshow' => false])->assertOk();
        $this->assertCount(0, $this->slides());
        $this->patchJson('/api/v1/admin/contents/'.$id, ['show_in_slideshow' => true, 'status' => 'archived'])->assertOk();
        $this->assertCount(0, $this->slides());
        $this->patchJson('/api/v1/admin/contents/'.$id, ['status' => 'published'])->assertOk();
        $this->assertCount(1, $this->slides());
        $this->deleteJson('/api/v1/admin/contents/'.$id)->assertNoContent();
        $this->assertCount(0, $this->slides());
        $this->postJson('/api/v1/admin/contents/'.$id.'/restore')->assertOk();
        $this->assertCount(1, $this->slides());
    }

    public function test_drafts_scheduled_expired_and_other_locales_never_leak_into_the_slideshow(): void
    {
        foreach ([['status' => 'draft'], ['published_at' => now()->addDay()->toIso8601String()], ['published_at' => now()->subDays(2)->toIso8601String(), 'expires_at' => now()->subDay()->toIso8601String()], ['locale' => 'en']] as $attributes) $this->create($attributes);
        $this->assertCount(0, $this->slides());
        $scheduled = Content::where('published_at', '>', now())->firstOrFail();
        $this->travel(25)->hours();
        $this->assertSame($scheduled->id, $this->slides()[0]['content_id']);
    }

    public function test_promotion_requires_a_public_image_and_allows_removing_it_after_unchecking(): void
    {
        $this->postJson('/api/v1/admin/contents', ['type' => 'news', 'title' => 'بدون تصویر', 'body' => 'متن', 'show_in_slideshow' => true])->assertUnprocessable()->assertJsonValidationErrors('cover_media_id');
        $id = $this->create()->json('data.id');
        $this->patchJson('/api/v1/admin/contents/'.$id, ['cover_media_id' => null])->assertUnprocessable()->assertJsonValidationErrors('cover_media_id');
        $this->patchJson('/api/v1/admin/contents/'.$id, ['show_in_slideshow' => false, 'cover_media_id' => null])->assertOk();
        $this->assertCount(0, $this->slides());
        $id = $this->create()->json('data.id');
        $this->image->update(['visibility' => 'private']);
        $this->assertCount(0, $this->slides());
    }

    public function test_county_and_province_homepages_keep_promoted_content_isolated(): void
    {
        $id = $this->create()->json('data.id');
        $this->assertSame($id, $this->slides()[0]['content_id']);
        $other = Organization::where('type', 'county')->where('id', '!=', $this->actor->organization_id)->firstOrFail();
        $this->getJson('/api/v1/portals/'.$other->slug)->assertOk()->assertJsonCount(0, 'data.slides');
        $this->getJson('/api/v1/home')->assertOk()->assertJsonCount(0, 'data.slides');
        $province = Organization::where('type', 'governorate')->firstOrFail();
        $publicImage = $this->image->replicate(); $publicImage->organization_id = $province->id; $publicImage->save();
        $provinceContent = Content::create(['author_id' => $this->actor->id, 'organization_id' => $province->id, 'type' => 'announcement', 'title' => 'اطلاعیه استان', 'slug' => 'province-fixture', 'body' => 'متن', 'cover_media_id' => $publicImage->id, 'status' => 'published', 'published_at' => now(), 'show_in_slideshow' => true]);
        $this->getJson('/api/v1/home?organization_id='.$this->actor->organization_id)->assertOk()->assertJsonCount(1, 'data.slides')->assertJsonPath('data.slides.0.content_id', $provinceContent->id);
        $this->patchJson('/api/v1/admin/contents/'.$provinceContent->id, ['show_in_slideshow' => false])->assertNotFound();
        $this->assertCount(1, $this->slides());
    }

    public function test_an_existing_active_manual_slide_for_the_same_content_is_not_duplicated(): void
    {
        $id = $this->create()->json('data.id');
        $slide = Slide::create(['organization_id' => $this->actor->organization_id, 'title' => 'اسلاید دستی', 'media_id' => $this->image->id, 'content_id' => $id, 'kind' => 'slide', 'is_active' => true]);
        $this->assertCount(1, $this->slides());
        $this->assertSame($slide->id, $this->slides()[0]['id']);
        $slide->update(['is_active' => false]);
        $this->assertCount(1, $this->slides());
        $this->assertSame('content-'.$id, $this->slides()[0]['id']);
    }
}
