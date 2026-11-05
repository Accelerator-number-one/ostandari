<?php

namespace Tests\Feature;

use App\Models\{Link, Media, User};
use App\Services\PortalIcons;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class LinkIconTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'admin')->firstOrFail(), 'web');
    }

    public function test_named_icons_are_saved_as_public_media_and_custom_gallery_images_can_replace_them(): void
    {
        $response = $this->postJson('/web-api/v1/admin/links', ['title' => 'درگاه خانه', 'url' => 'https://example.ir',
            'position' => 'useful', 'is_active' => true, 'icon' => 'home'])->assertCreated();
        $id = $response->json('data.id');
        $response->assertJsonPath('data.icon', 'home')->assertJsonPath('data.image.url', '/design/icons/home.svg');
        $this->get('/links')->assertOk()->assertSee('/design/icons/home.svg', false);
        $this->get('/')->assertOk()->assertSee('/design/icons/home.svg', false);
        $user = User::where('username', 'admin')->firstOrFail();
        $photo = Media::create(['user_id' => $user->id, 'organization_id' => $user->organization_id, 'disk' => 'public',
            'path' => 'gallery/custom-icon.webp', 'original_name' => 'custom.webp', 'mime' => 'image/webp', 'size' => 80, 'visibility' => 'public']);
        $this->patchJson('/web-api/v1/admin/links/'.$id, ['icon' => '', 'media_id' => $photo->id])->assertOk()
            ->assertJsonPath('data.icon', null)->assertJsonPath('data.media_id', $photo->id);
        $this->assertSame($photo->id, Link::findOrFail($id)->media_id);
    }

    public function test_invalid_icons_are_rejected_and_deleting_an_unused_icon_keeps_bundled_assets(): void
    {
        $this->postJson('/web-api/v1/admin/links', ['title' => 'پیوند', 'url' => 'https://example.ir',
            'icon' => '../../private'])->assertUnprocessable();
        $this->assertSame(0, Link::count());
        $actor = User::where('username', 'admin')->firstOrFail();
        $media = PortalIcons::media('help', $actor, $actor->organization_id);
        $file = public_path('design/icons/help.svg');
        $before = hash_file('sha256', $file);
        $this->deleteJson('/web-api/v1/media/'.$media->id)->assertNoContent();
        $this->assertSoftDeleted('media', ['id' => $media->id]);
        $this->assertSame($before, hash_file('sha256', $file));
    }
}
