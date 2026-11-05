<?php

namespace Tests\Feature;

use App\Models\{Media, Organization, Role, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
    }

    private function upload(UploadedFile $file, string $visibility = 'public')
    {
        return $this->post('/api/v1/media', ['file' => $file, 'visibility' => $visibility, 'alt' => 'شرح تصویر'], ['Accept' => 'application/json']);
    }

    private function savedImage(int $id): array
    {
        $media = Media::findOrFail($id);
        $bytes = Storage::disk($media->disk)->get($media->path);
        $this->assertSame('image/webp', $media->mime);
        $this->assertStringEndsWith('.webp', $media->path);
        $this->assertSame(strlen($bytes), $media->size);
        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring($bytes)[2]);
        return [$media, imagecreatefromstring($bytes)];
    }

    public function test_jpeg_upload_is_a_real_resized_webp_with_matching_metadata_and_download_name(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('سفر استاندار.jpg', 2400, 1200))->assertCreated()->json('data.id');
        [$media, $image] = $this->savedImage($id);
        $this->assertSame(2000, imagesx($image));
        $this->assertSame(1000, imagesy($image));
        $this->assertSame([2000, 1000], [$media->width, $media->height]);
        $this->assertSame('سفر استاندار.webp', $media->original_name);
        $this->assertSame('شرح تصویر', $media->alt);
        $this->assertStringEndsWith('.webp', $media->url);
        $this->get('/api/v1/media/'.$id.'/download')->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_transparent_png_preserves_alpha_and_small_images_are_not_upscaled(): void
    {
        $image = imagecreatetruecolor(24, 12);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesetpixel($image, 12, 6, imagecolorallocatealpha($image, 255, 0, 0, 0));
        ob_start(); imagepng($image); $png = ob_get_clean();
        $id = $this->upload(UploadedFile::fake()->createWithContent('transparent.png', $png))->assertCreated()->json('data.id');
        [$media, $output] = $this->savedImage($id);
        $this->assertSame([24, 12], [$media->width, $media->height]);
        $this->assertSame(127, imagecolorsforindex($output, imagecolorat($output, 0, 0))['alpha']);
        $this->assertSame(0, imagecolorsforindex($output, imagecolorat($output, 12, 6))['alpha']);
    }

    public static function orientations(): array
    {
        // Color order is top left, top right, bottom left, bottom right.
        return [
            [1, 80, 40, ['r', 'g', 'b', 'y']], [2, 80, 40, ['g', 'r', 'y', 'b']],
            [3, 80, 40, ['y', 'b', 'g', 'r']], [4, 80, 40, ['b', 'y', 'r', 'g']],
            [5, 40, 80, ['r', 'b', 'g', 'y']], [6, 40, 80, ['b', 'r', 'y', 'g']],
            [7, 40, 80, ['y', 'g', 'b', 'r']], [8, 40, 80, ['g', 'y', 'r', 'b']],
        ];
    }

    #[DataProvider('orientations')]
    public function test_phone_exif_orientation_is_applied_to_the_pixels(int $orientation, int $width, int $height, array $expected): void
    {
        $image = imagecreatetruecolor(80, 40);
        $colors = ['r' => [255, 0, 0], 'g' => [0, 255, 0], 'b' => [0, 0, 255], 'y' => [255, 255, 0]];
        foreach (array_keys($colors) as $i => $key) {
            $x = ($i % 2) * 40; $y = intdiv($i, 2) * 20;
            imagefilledrectangle($image, $x, $y, $x + 39, $y + 19, imagecolorallocate($image, ...$colors[$key]));
        }
        ob_start(); imagejpeg($image, null, 100); $jpeg = ob_get_clean();
        $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0".pack('vvVvvV', 0x0112, 3, 1, $orientation, 0, 0);
        $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $id = $this->upload(UploadedFile::fake()->createWithContent('phone.jpg', $jpeg))->assertCreated()->json('data.id');
        [$media, $output] = $this->savedImage($id);
        $this->assertSame([$width, $height], [$media->width, $media->height]);
        foreach ($expected as $i => $key) {
            $actual = imagecolorsforindex($output, imagecolorat($output, (int) ($width * (($i % 2) ? .75 : .25)), (int) ($height * ($i < 2 ? .25 : .75))));
            foreach (['red', 'green', 'blue'] as $channel => $name) {
                $this->assertLessThan(35, abs($actual[$name] - $colors[$key][$channel]));
            }
        }
    }

    public function test_existing_webp_is_preserved_without_another_lossy_conversion(): void
    {
        $image = imagecreatetruecolor(16, 12);
        ob_start(); imagewebp($image, null, 84); $bytes = ob_get_clean();
        $id = $this->upload(UploadedFile::fake()->createWithContent('already.webp', $bytes))->assertCreated()->json('data.id');
        [$media] = $this->savedImage($id);
        $this->assertSame($bytes, Storage::disk($media->disk)->get($media->path));
    }

    public function test_citizen_private_image_is_converted_but_cannot_be_read_by_another_citizen_or_published(): void
    {
        $citizen = User::create(['name' => 'Image owner', 'person_type' => 'citizen', 'role_id' => Role::where('name', 'citizen')->value('id'), 'is_active' => true]);
        Sanctum::actingAs($citizen);
        $response = $this->upload(UploadedFile::fake()->image('report.png', 18, 12), 'private')->assertCreated()->assertJsonPath('data.url', null);
        $id = $response->json('data.id');
        [$media] = $this->savedImage($id);
        $this->assertSame('local', $media->disk);
        $this->assertSame($citizen->id, $media->user_id);
        $this->get('/api/v1/media/'.$id.'/download')->assertOk();
        $this->upload(UploadedFile::fake()->image('no-public.png'))->assertForbidden();
        Sanctum::actingAs(User::create(['name' => 'Other citizen', 'person_type' => 'citizen', 'role_id' => $citizen->role_id, 'is_active' => true]));
        $this->getJson('/api/v1/media/'.$id.'/download')->assertNotFound();
    }

    public function test_county_staff_gets_webp_owned_by_their_organization(): void
    {
        $organization = Organization::where('type', 'county')->firstOrFail();
        $staff = User::create(['name' => 'County PR', 'person_type' => 'staff', 'organization_id' => $organization->id, 'access_scope' => 'organization', 'role_id' => Role::where('name', 'county_public_relations')->value('id'), 'is_active' => true]);
        Sanctum::actingAs($staff);
        $id = $this->upload(UploadedFile::fake()->image('county.png', 20, 10))->assertCreated()->assertJsonPath('data.organization_id', $organization->id)->json('data.id');
        $this->savedImage($id);
    }

    public function test_non_image_attachments_keep_their_format_and_bytes(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $id = $this->upload(UploadedFile::fake()->createWithContent('document.pdf', $pdf))->assertCreated()->json('data.id');
        $media = Media::findOrFail($id);
        $this->assertSame('application/pdf', $media->mime);
        $this->assertSame('document.pdf', $media->original_name);
        $this->assertNull($media->width);
        $this->assertSame($pdf, Storage::disk('public')->get($media->path));
    }

    public function test_corrupt_disallowed_and_excessive_dimension_images_leave_no_files_or_rows(): void
    {
        $png = UploadedFile::fake()->image('valid.png', 12, 12);
        $broken = substr(file_get_contents($png->getRealPath()), 0, 40);
        $this->upload(UploadedFile::fake()->createWithContent('broken.png', $broken))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload(UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'))->assertUnprocessable();
        config(['portal.images.max_pixels' => 100]);
        $this->upload(UploadedFile::fake()->image('large.png', 12, 12))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_storage_failure_removes_partial_output_and_the_conversion_temporary_file(): void
    {
        $file = UploadedFile::fake()->image('failure.png', 12, 12);
        $before = glob(sys_get_temp_dir().'/portal-webp-*');
        // Fail only the storage boundary, after real GD conversion.
        $disk = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $this->upload($file)->assertStatus(503);
        $this->assertSame($before, glob(sys_get_temp_dir().'/portal-webp-*'));
        $this->assertDatabaseCount('media', 0);
    }

    public function test_database_failure_removes_the_stored_webp_and_temporary_file(): void
    {
        $before = glob(sys_get_temp_dir().'/portal-webp-*');
        Media::creating(fn () => throw new \RuntimeException('Simulated database failure'));
        $this->upload(UploadedFile::fake()->image('failure.png', 12, 12))->assertStatus(500);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($before, glob(sys_get_temp_dir().'/portal-webp-*'));
        $this->assertDatabaseCount('media', 0);
    }

    public function test_media_search_uses_converted_name_and_filters_images_and_files(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('searchable-photo.png', 12, 12))->assertCreated()->json('data.id');
        $this->upload(UploadedFile::fake()->createWithContent('notes.txt', 'fixture text'))->assertCreated();
        $this->getJson('/api/v1/media?kind=image&q=searchable-photo.webp')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/media?kind=file')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.mime', 'text/plain');
        $this->getJson('/api/v1/media?kind=other')->assertUnprocessable();
    }

    public function test_media_search_cannot_expose_another_citizens_private_filename_or_alt(): void
    {
        $citizen = User::create(['name' => 'Owner', 'person_type' => 'citizen', 'role_id' => Role::where('name', 'citizen')->value('id'), 'is_active' => true]);
        Sanctum::actingAs($citizen);
        $this->upload(UploadedFile::fake()->image('private-match.png', 12, 12), 'private')->assertCreated();
        Sanctum::actingAs(User::create(['name' => 'Other', 'person_type' => 'citizen', 'role_id' => $citizen->role_id, 'is_active' => true]));
        $this->getJson('/api/v1/media?q=private-match')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/media?q='.urlencode('شرح تصویر'))->assertOk()->assertJsonCount(0, 'data');
    }

}
