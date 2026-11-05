<?php

namespace Tests\Feature;

use App\Models\{Media, Organization, Role, Setting, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AnnualSloganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    private function provincial(): User
    {
        return User::create(['name' => 'Province fixture', 'username' => 'slogan_pr', 'password' => 'Fixture-Password42!',
            'person_type' => 'staff', 'organization_id' => Organization::where('type', 'governorate')->firstOrFail()->id,
            'role_id' => Role::where('name', 'province_public_relations')->firstOrFail()->id, 'access_scope' => 'organization']);
    }

    public function test_provincial_pr_can_replace_only_the_annual_slogan_and_public_reads_use_it(): void
    {
        $user = $this->provincial(); Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.portal_context.can_write', false)
            ->assertJsonPath('data.portal_context.can_manage_annual_slogan', true);
        $this->getJson('/api/v1/admin/annual-slogan')->assertOk();
        $response = $this->post('/api/v1/admin/annual-slogan', ['file' => UploadedFile::fake()->image('year.png', 240, 100)], ['Accept' => 'application/json'])->assertOk();
        $id = $response->json('data.id'); $url = $response->json('data.url');
        $media = Media::findOrFail($id);
        $this->assertSame('image/webp', $media->mime);
        $this->assertSame($user->organization_id, $media->organization_id);
        Storage::disk('public')->assertExists($media->getRawOriginal('path'));
        $this->assertSame($url, $this->getJson('/api/v1/settings')->assertOk()->json('data')['site.slogan']['url']);
        $this->assertSame($url, $this->getJson('/api/v1/home')->assertOk()->json('data.settings')['site.slogan']['url']);
        $this->postJson('/api/v1/admin/contents', [])->assertForbidden();
        $this->postJson('/api/v1/media', [])->assertForbidden();
        $this->putJson('/api/v1/admin/settings', ['settings' => [['key' => 'site.name', 'value' => 'forged', 'is_public' => true]]])->assertForbidden();
    }

    public function test_county_pr_citizens_and_unrelated_staff_cannot_change_provincial_branding(): void
    {
        foreach ([
            ['county_public_relations', Organization::where('type', 'county')->firstOrFail(), 'staff'],
            ['province_public_relations', Organization::where('type', 'governorate')->firstOrFail(), 'citizen'],
            ['ticket_agent', Organization::where('type', 'governorate')->firstOrFail(), 'staff'],
        ] as $index => [$role, $organization, $personType]) {
            $roleId = Role::where('name', $role)->value('id') ?? Role::create(['name' => 'slogan_agent', 'label' => 'Fixture', 'permissions' => ['dashboard.view']])->id;
            $user = User::create(['name' => 'Fixture '.$index, 'username' => 'denied_slogan_'.$index, 'password' => 'Fixture-Password42!',
                'person_type' => $personType, 'organization_id' => $organization->id, 'role_id' => $roleId]);
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/admin/annual-slogan')->assertForbidden();
            $this->post('/api/v1/admin/annual-slogan', ['file' => UploadedFile::fake()->image('year.png')], ['Accept' => 'application/json'])->assertForbidden();
        }
        $this->assertFalse(Setting::where('key', 'site.slogan')->exists());
    }

    public function test_missing_and_non_image_uploads_do_not_replace_the_current_slogan(): void
    {
        Sanctum::actingAs($this->provincial());
        Setting::create(['key' => 'site.slogan', 'value' => '/fixture/old.webp', 'is_public' => true]);
        $this->postJson('/api/v1/admin/annual-slogan', [])->assertUnprocessable();
        $this->post('/api/v1/admin/annual-slogan', ['file' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame('/fixture/old.webp', Setting::where('key', 'site.slogan')->value('value'));
    }

    public function test_global_admin_can_replace_the_slogan_and_current_media_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $old = $this->post('/api/v1/admin/annual-slogan', ['file' => UploadedFile::fake()->image('first.png')], ['Accept' => 'application/json'])->assertOk()->json('data.id');
        $this->deleteJson('/api/v1/media/'.$old)->assertStatus(409);
        $new = $this->post('/api/v1/admin/annual-slogan', ['file' => UploadedFile::fake()->image('second.png')], ['Accept' => 'application/json'])->assertOk()->json('data.id');
        $this->assertNotSame($old, $new);
        $this->deleteJson('/api/v1/media/'.$old)->assertNoContent();
        $this->deleteJson('/api/v1/media/'.$new)->assertStatus(409);
    }
}
