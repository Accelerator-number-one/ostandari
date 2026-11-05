<?php

namespace Tests\Feature;

use App\Models\{Media, User};
use App\Services\PublicImages;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PublicImagesTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
        Storage::fake('local');
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
    }

    private function upload(string $name = 'governor.jpg', string $visibility = 'public')
    {
        return $this->post('/api/v1/media', ['file'=>UploadedFile::fake()->image($name, 60, 80), 'visibility'=>$visibility], ['Accept'=>'application/json']);
    }

    private function legacy(string $path = '2026/10/legacy.webp'): Media
    {
        $image = imagecreatetruecolor(24, 32);
        ob_start(); imagewebp($image); $bytes = ob_get_clean();
        Storage::disk('public')->put($path, $bytes);
        return Media::create(['user_id'=>auth()->id(), 'organization_id'=>auth()->user()->organization_id, 'disk'=>'public',
            'path'=>$path, 'original_name'=>'legacy.webp', 'mime'=>'image/webp', 'size'=>strlen($bytes), 'visibility'=>'public']);
    }

    public function test_two_governor_portraits_survive_complete_loss_of_local_files_and_keep_their_references(): void
    {
        $images = [];
        foreach (['نفر اول', 'نفر دوم'] as $name) {
            $upload = $this->upload()->assertCreated()->json('data');
            $media = Media::findOrFail($upload['id']);
            $images[] = [$media, Storage::disk('public')->get($media->path)];
            $member = $this->postJson('/api/v1/admin/members', ['name'=>$name, 'position'=>'استاندار', 'kind'=>'governor',
                'media_id'=>$media->id, 'is_current'=>false, 'is_active'=>true])->assertCreated()->json('data');
            $this->get('/governors')->assertOk()->assertSee($upload['url'], false);
            $this->get('/managers?person='.$member['id'])->assertOk()->assertSee($upload['url'], false);
        }
        Storage::disk('public')->deleteDirectory('2026');
        Storage::fake('public'); // A second instance has no filesystem shared with the uploader.
        foreach ($images as [$media, $bytes]) {
            $this->assertDatabaseHas('public_media_files', ['media_id'=>$media->id, 'sha256'=>hash('sha256', $bytes)]);
            $this->assertStringStartsWith('/media-assets/', $media->url);
            foreach ([$media->url, '/storage/'.$media->path, $media->url] as $url)
                $this->get($url)->assertOk()->assertHeader('Content-Type','image/webp')->assertHeader('X-Content-Type-Options','nosniff')->assertContent($bytes);
            $this->get('/api/v1/media/'.$media->id.'/download')->assertOk()->assertStreamedContent($bytes);
        }
    }

    public function test_public_images_have_no_session_cookie_and_support_conditional_requests(): void
    {
        $upload = $this->upload()->assertCreated()->json('data');
        $response = $this->get($upload['url'])->assertOk();
        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->get($upload['url'], ['If-None-Match'=>$response->headers->get('ETag')])->assertStatus(304)->assertContent('');
        $this->head($upload['url'])->assertOk();
    }

    public function test_private_deleted_wrong_name_and_non_image_files_are_not_exposed(): void
    {
        $upload = $this->upload()->assertCreated()->json('data');
        $media = Media::findOrFail($upload['id']);
        $this->get('/media-assets/'.$media->id.'/wrong.webp')->assertNotFound();
        $media->visibility = 'private'; $media->save();
        $this->get($upload['url'])->assertNotFound();
        $this->get('/storage/'.$media->path)->assertNotFound();
        $media->visibility = 'public'; $media->save();
        $this->deleteJson('/api/v1/media/'.$media->id)->assertNoContent();
        $this->get($upload['url'])->assertNotFound();
        $this->get('/storage/'.$media->path)->assertNotFound();
        $private = $this->upload('private.png','private')->assertCreated()->assertJsonPath('data.url',null)->json('data');
        $this->assertDatabaseMissing('public_media_files', ['media_id'=>$private['id']]);
        $this->get('/media-assets/'.$private['id'].'/'.basename(Media::findOrFail($private['id'])->path))->assertNotFound();
        $file = Media::create(['user_id'=>auth()->id(), 'disk'=>'public', 'path'=>'2026/10/not-image.webp', 'original_name'=>'file.txt', 'mime'=>'text/plain', 'size'=>4, 'visibility'=>'public']);
        $this->get('/media-assets/'.$file->id.'/not-image.webp')->assertNotFound();
    }

    public function test_legacy_images_are_preserved_without_changing_paths_or_overwriting_originals(): void
    {
        $media = $this->legacy();
        $bytes = Storage::disk('public')->get($media->path);
        $this->artisan('portal:prepare-public-images')->assertSuccessful();
        $this->assertSame($bytes, PublicImages::stored($media));
        Storage::disk('public')->put($media->path, 'later corrupt filesystem copy');
        $this->artisan('portal:prepare-public-images')->assertSuccessful();
        $this->assertSame($bytes, PublicImages::stored($media));
        Storage::disk('public')->delete($media->path);
        $this->get($media->url)->assertOk()->assertContent($bytes);
        $this->get('/storage/'.$media->path)->assertOk()->assertContent($bytes);
        $this->assertSame('2026/10/legacy.webp', $media->fresh()->path);
        $this->assertDatabaseCount('public_media_files', 1);
    }

    public function test_first_public_read_preserves_a_legacy_local_image_and_missing_originals_are_reported(): void
    {
        $media = $this->legacy();
        $bytes = Storage::disk('public')->get($media->path);
        $this->get($media->url)->assertOk()->assertContent($bytes);
        $this->assertSame($bytes, PublicImages::stored($media));
        $missing = $this->legacy('2026/10/missing.webp');
        Storage::disk('public')->delete($missing->path);
        $this->artisan('portal:prepare-public-images')->expectsOutputToContain('Original image unavailable: media '.$missing->id)->assertSuccessful();
        $this->get($missing->url)->assertNotFound()->assertHeader('Cache-Control','no-store, private');
        $this->assertDatabaseMissing('public_media_files', ['media_id'=>$missing->id]);
    }

    public function test_schema_not_ready_rejects_public_uploads_instead_of_reporting_a_temporary_file_as_saved(): void
    {
        Schema::drop('public_media_files');
        DB::table('migrations')->where('migration','2026_10_30_000004_create_public_media_files_table')->delete();
        $before = glob(sys_get_temp_dir().'/portal-webp-*');
        $this->upload()->assertStatus(503);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 0);
        $this->assertSame($before, glob(sys_get_temp_dir().'/portal-webp-*'));
        $this->upload('private.png','private')->assertCreated();
        $this->artisan('portal:prepare-public-images')->assertSuccessful();
        $this->upload()->assertCreated();
    }

    public function test_durable_write_failure_rolls_back_the_media_row_and_cleans_the_local_upload(): void
    {
        DB::statement('ALTER TABLE public_media_files RENAME COLUMN base64 TO unavailable_payload');
        $before = glob(sys_get_temp_dir().'/portal-webp-*');
        $this->upload()->assertStatus(500);
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($before, glob(sys_get_temp_dir().'/portal-webp-*'));
    }
}
