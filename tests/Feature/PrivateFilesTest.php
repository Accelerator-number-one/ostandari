<?php

namespace Tests\Feature;

use App\Models\{Media, Role, User};
use App\Services\PrivateFiles;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PrivateFilesTest extends TestCase
{
    use DatabaseMigrations;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Storage::fake('public');
        $this->owner=User::create(['name'=>'Private file owner','person_type'=>'citizen','role_id'=>Role::where('name','citizen')->value('id')]);
        Sanctum::actingAs($this->owner);
    }

    private function upload(UploadedFile $file)
    {
        return $this->post('/api/v1/media',['visibility'=>'private','file'=>$file],['Accept'=>'application/json']);
    }

    public function test_image_and_pdf_survive_instance_replacement_and_remain_authenticated_private_downloads(): void
    {
        $pdf="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $files=[];
        foreach([UploadedFile::fake()->image('evidence.jpg',30,40),UploadedFile::fake()->createWithContent('document.pdf',$pdf)] as $file) {
            $row=$this->upload($file)->assertCreated()->assertJsonPath('data.url',null)->assertJsonMissingPath('data.base64')->json('data');
            $media=Media::findOrFail($row['id']);
            $files[]=[$media,Storage::disk('local')->get($media->path)];
        }
        Storage::fake('local');
        foreach($files as [$media,$bytes]) {
            $this->get('/api/v1/media/'.$media->id.'/download')->assertOk()->assertStreamedContent($bytes)
                ->assertHeader('Content-Type',$media->mime)->assertHeader('X-Content-Type-Options','nosniff');
            $this->get('/media-assets/'.$media->id.'/'.basename($media->path))->assertNotFound();
            $this->get('/storage/'.$media->path)->assertNotFound();
        }
        $other=User::create(['name'=>'Other citizen','person_type'=>'citizen','role_id'=>$this->owner->role_id]);
        Sanctum::actingAs($other);
        foreach($files as [$media])$this->getJson('/api/v1/media/'.$media->id.'/download')->assertNotFound();
    }

    public function test_available_legacy_private_files_are_preserved_without_overwriting_the_original_or_owner(): void
    {
        $bytes='Original private evidence';
        $media=Media::create(['user_id'=>$this->owner->id,'disk'=>'local','visibility'=>'private','path'=>'legacy/evidence.txt','original_name'=>'evidence.txt','mime'=>'text/plain','size'=>strlen($bytes)]);
        Storage::disk('local')->put($media->path,$bytes);
        $this->artisan('portal:prepare-private-files')->assertSuccessful();
        Storage::disk('local')->put($media->path,'A changed filesystem copy');
        $this->artisan('portal:prepare-private-files')->assertSuccessful();
        Storage::fake('local');
        $this->assertSame($bytes,PrivateFiles::stored($media));
        $this->get('/api/v1/media/'.$media->id.'/download')->assertOk()->assertStreamedContent($bytes);
        $this->assertSame($this->owner->id,$media->fresh()->user_id);
        $this->assertSame('legacy/evidence.txt',$media->fresh()->path);
        $this->assertNull($media->fresh()->url);
        $this->assertDatabaseCount('private_media_files',1);
    }

    public function test_missing_storage_schema_rejects_uploads_until_prepared_without_leaving_temporary_success_records(): void
    {
        Schema::drop('private_media_files');
        DB::table('migrations')->where('migration','2026_10_31_000001_create_private_media_files_table')->delete();
        $this->upload(UploadedFile::fake()->image('not-ready.jpg',12,16))->assertStatus(503);
        $this->assertDatabaseCount('media',0);
        $this->assertSame([],Storage::disk('local')->allFiles());
        $this->artisan('portal:prepare-private-files')->assertSuccessful();
        $this->upload(UploadedFile::fake()->image('ready.jpg',12,16))->assertCreated();
    }

    public function test_durable_write_failure_rolls_back_metadata_and_removes_the_file_and_conversion_output(): void
    {
        DB::statement('ALTER TABLE private_media_files RENAME COLUMN base64 TO unavailable_payload');
        $before=glob(sys_get_temp_dir().'/portal-webp-*');
        $this->upload(UploadedFile::fake()->image('failure.png',12,16))->assertStatus(500);
        $this->assertDatabaseCount('media',0);
        $this->assertSame([],Storage::disk('local')->allFiles());
        $this->assertSame($before,glob(sys_get_temp_dir().'/portal-webp-*'));
    }
}
