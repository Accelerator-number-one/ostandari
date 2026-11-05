<?php

namespace Tests\Feature;

use App\Models\{Media, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

final class MediaRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    private function original(string $disk, string $path, string $bytes): Media
    {
        $media = Media::create([
            'user_id' => User::where('username', 'admin')->value('id'),
            'disk' => $disk, 'path' => $path,
            'original_name' => basename($path), 'mime' => 'application/pdf', 'size' => strlen($bytes),
            'visibility' => $disk === 'public' ? 'public' : 'private',
        ]);
        DB::table($disk === 'public' ? 'public_media_files' : 'private_media_files')->insert([
            'media_id' => $media->id, 'base64' => base64_encode($bytes), 'sha256' => hash('sha256', $bytes),
        ]);
        return $media;
    }

    public function test_full_backup_files_are_restored_into_the_correct_disk_and_repeat_safely(): void
    {
        $public = $this->original('public', '2026/11/public.pdf', 'public-original');
        $private = $this->original('local', '2026/11/private.pdf', 'private-original');
        $this->artisan('portal:restore-media')->assertSuccessful();
        $this->assertSame('public-original', Storage::disk('public')->get($public->path));
        $this->assertSame('private-original', Storage::disk('local')->get($private->path));
        Storage::disk('public')->assertMissing($private->path);
        $this->artisan('portal:restore-media')->assertSuccessful();
        $this->assertDatabaseCount('media', 2);
    }

    public function test_existing_different_files_are_preserved_without_the_explicit_overwrite_option(): void
    {
        $media = $this->original('public', 'uploads/original.pdf', 'from-sql');
        Storage::disk('public')->put($media->path, 'local-change');
        $this->artisan('portal:restore-media')->assertFailed();
        $this->assertSame('local-change', Storage::disk('public')->get($media->path));
        $this->artisan('portal:restore-media', ['--overwrite' => true])->assertSuccessful();
        $this->assertSame('from-sql', Storage::disk('public')->get($media->path));
    }

    public function test_invalid_checksums_and_paths_are_reported_and_never_written(): void
    {
        $corrupt = $this->original('public', 'uploads/corrupt.pdf', 'expected');
        DB::table('public_media_files')->where('media_id', $corrupt->id)->update(['base64' => base64_encode('modified')]);
        $this->original('public', '../escaped.pdf', 'escape');
        $this->artisan('portal:restore-media')->assertFailed();
        Storage::disk('public')->assertMissing('uploads/corrupt.pdf');
        $this->assertFileDoesNotExist(dirname(Storage::disk('public')->path('')).'/escaped.pdf');
    }
}
