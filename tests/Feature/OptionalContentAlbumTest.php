<?php

namespace Tests\Feature;

use App\Models\{Content, Media, Organization, Role, Tag, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class OptionalContentAlbumTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actor = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($this->actor);
        Storage::fake('public');
    }

    private function create(array $extra = [])
    {
        return $this->postJson('/api/v1/admin/contents', $extra + ['type' => 'news', 'title' => 'محتوای آزمایشی', 'body' => '<p>متن کامل</p>']);
    }

    private function image(string $name = 'fixture.png'): array
    {
        return $this->post('/api/v1/media', ['file' => UploadedFile::fake()->image($name, 30, 20), 'visibility' => 'public'], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    public function test_optional_relations_and_nullable_fields_can_be_left_empty_for_news_announcements_and_resolutions(): void
    {
        foreach (['news', 'announcement', 'resolution'] as $type) {
            foreach ([[], ['tag_ids' => [], 'media_ids' => []], ['tag_ids' => null, 'media_ids' => null], ['tag_ids' => '', 'media_ids' => ''], ['tag_ids' => ['', null, '  '], 'media_ids' => ['', null, ' ']]] as $empty) {
                $id = $this->create($empty + ['type' => $type, 'subtitle' => null, 'excerpt' => null, 'category_id' => null, 'cover_media_id' => null, 'published_at' => null, 'expires_at' => null])->assertCreated()->json('data.id');
                $this->assertSame(0, Content::findOrFail($id)->tags()->count());
                $this->assertSame(0, Content::findOrFail($id)->media()->count());
            }
        }
    }

    public function test_omitting_relations_preserves_real_selections_and_explicit_empty_selection_clears_them(): void
    {
        $tag = Tag::create(['name' => 'برچسب واقعی', 'slug' => 'album-tag', 'locale' => 'fa']);
        $image = $this->image();
        $id = $this->create(['tag_ids' => [$tag->id], 'media_ids' => [$image['id']]])->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/contents/'.$id, ['title' => 'عنوان تازه'])->assertOk();
        $this->assertSame([$tag->id], Content::findOrFail($id)->tags()->pluck('tags.id')->all());
        $this->assertSame([$image['id']], Content::findOrFail($id)->media()->pluck('media.id')->all());
        $this->patchJson('/api/v1/admin/contents/'.$id, ['tag_ids' => [null, ''], 'media_ids' => null])->assertOk();
        $this->assertSame(0, Content::findOrFail($id)->tags()->count());
        $this->assertSame(0, Content::findOrFail($id)->media()->count());
    }

    public function test_actual_invalid_ids_duplicates_and_required_content_still_fail_validation(): void
    {
        foreach (['tag_ids', 'media_ids'] as $field) {
            foreach ([[0], ['bogus'], [999999999]] as $ids) {
                $this->create([$field => $ids])->assertUnprocessable()->assertJsonValidationErrors($field.'.0');
            }
        }
        $tag = Tag::create(['name' => 'تکراری', 'slug' => 'duplicate-fixture', 'locale' => 'fa']);
        $this->create(['tag_ids' => [$tag->id, $tag->id]])->assertUnprocessable()->assertJsonValidationErrors('tag_ids.0');
        $this->create(['title' => ''])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->create(['body' => '<p><br></p>'])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_uploaded_webp_album_survives_save_edit_public_read_and_tracks_inline_images_separately(): void
    {
        $images = [$this->image('one.png'), $this->image('two.png'), $this->image('three.png')];
        $body = '<p>گزارش تصویری</p><div class="portal-image-album portal-image-album-columns-3 arbitrary" onclick="alert(1)" style="display:none" data-command="deleteRecord">';
        foreach ($images as $image) {
            $this->assertSame('image/webp', $image['mime']);
            $body .= '<div class="portal-image-album-item"><img src="'.$image['url'].'" alt="تصویر خبر" onerror="alert(1)"></div>';
        }
        $body .= '<p class="portal-image-album-caption">شرح آلبوم</p></div><script>alert(1)</script>';
        $response = $this->create(['body' => $body, 'status' => 'published', 'tag_ids' => [], 'media_ids' => []])->assertCreated();
        $id = $response->json('data.id');
        $clean = $response->json('data.body');
        $this->assertSame(3, substr_count($clean, '<img'));
        foreach (['portal-image-album-columns-3', 'portal-image-album-item', 'portal-image-album-caption', 'شرح آلبوم'] as $text) $this->assertStringContainsString($text, $clean);
        foreach (['arbitrary', 'onclick', 'onerror', 'style=', 'data-command', '<script'] as $text) $this->assertStringNotContainsString($text, $clean);
        $this->assertSame(['inline', 'inline', 'inline'], DB::table('mediables')->where('mediable_type', (new Content)->getMorphClass())->where('mediable_id', $id)->pluck('purpose')->all());
        $this->patchJson('/api/v1/admin/contents/'.$id, ['body' => $clean, 'media_ids' => []])->assertOk()->assertJsonPath('data.body', $clean);
        $this->getJson('/api/v1/contents/'.$id)->assertOk()->assertJsonPath('data.content.body', $clean);
        $this->deleteJson('/api/v1/media/'.$images[0]['id'])->assertConflict();
        $this->patchJson('/api/v1/admin/contents/'.$id, ['body' => '<p>آلبوم برداشته شد</p>', 'media_ids' => []])->assertOk();
        foreach ($images as $image) $this->deleteJson('/api/v1/media/'.$image['id'])->assertNoContent();
    }

    public function test_an_album_cannot_reference_another_organizations_image(): void
    {
        $foreign = $this->image();
        $org = Organization::where('type', 'county')->whereHas('county', fn ($query) => $query->where('slug', 'amol'))->firstOrFail();
        $county = User::create(['name' => 'County PR', 'username' => 'album_county_fixture', 'password' => 'Fixture-Password42!', 'person_type' => 'staff', 'organization_id' => $org->id, 'role_id' => Role::where('name', 'county_public_relations')->value('id'), 'access_scope' => 'organization', 'is_active' => true]);
        Sanctum::actingAs($county);
        $before = Content::count();
        $body = '<div class="portal-image-album portal-image-album-columns-1"><div class="portal-image-album-item"><img src="'.$foreign['url'].'"></div></div>';
        $this->create(['body' => $body])->assertForbidden();
        $this->assertSame($before, Content::count(), 'An invalid album must roll back the whole save.');
    }
}
