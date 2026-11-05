<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\Gallery;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_local_account_command_resets_the_requested_credentials_and_login_works(): void
    {
        $this->artisan('portal:local-admin')->assertSuccessful();
        $user = User::where('username', 'admin')->firstOrFail();
        $this->assertTrue(Hash::check('admin', $user->password));
        $this->assertTrue($user->isSuperAdmin());
        $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'admin'])->assertOk()->assertJsonPath('data.user.username', 'admin');
        $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'incorrect'])->assertUnprocessable();
    }

    public function test_local_account_command_is_not_available_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('portal:local-admin')->assertFailed();
        $this->assertFalse(Hash::check('admin', User::where('username', 'admin')->firstOrFail()->password));
    }

    public function test_all_twenty_two_counties_have_a_portal_and_legacy_slugs_resolve(): void
    {
        $this->assertSame(22, County::count());
        foreach (County::all() as $county) {
            $this->getJson('/api/v1/counties/by-slug/'.$county->slug)->assertOk()->assertJsonPath('data.county.name', $county->name);
            $this->assertSame(1, Organization::where('county_id', $county->id)->where('type', 'county')->count());
        }
        $this->getJson('/api/v1/counties/by-slug/noor')->assertOk()->assertJsonPath('data.county.slug', 'nur');
        $this->getJson('/api/v1/counties/by-slug/gov_tonekabon')->assertOk()->assertJsonPath('data.county.slug', 'tonekabon');
        $this->getJson('/api/v1/counties/by-slug/not-a-county')->assertNotFound();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(22, County::count());
    }

    public function test_county_contact_is_delivered_to_that_county_and_invalid_county_is_rejected(): void
    {
        $body = ['name' => 'Test Person', 'email' => 'person@example.test', 'subject' => 'County message', 'body' => 'A message intended for the selected county office.', 'county_slug' => 'nur'];
        $reference = $this->postJson('/api/v1/contact', $body)->assertCreated()->json('data.reference');
        $county = County::where('slug', 'nur')->firstOrFail();
        $ticket = Ticket::where('reference', $reference)->firstOrFail();
        $this->assertSame($county->id, $ticket->county_id);
        $this->assertSame(Organization::where('county_id', $county->id)->value('id'), $ticket->organization_id);
        $body['county_slug'] = 'not-a-county';
        $this->postJson('/api/v1/contact', $body)->assertUnprocessable();
        $this->assertSame(1, Ticket::count());
    }

    public function test_county_gallery_filter_does_not_return_another_offices_images(): void
    {
        $nur = Organization::where('slug', 'county-nur')->firstOrFail();
        $sari = Organization::where('slug', 'county-sari')->firstOrFail();
        Gallery::create(['title' => 'Nur gallery', 'slug' => 'nur-gallery', 'organization_id' => $nur->id, 'status' => 'published', 'published_at' => now()->subMinute()]);
        Gallery::create(['title' => 'Sari gallery', 'slug' => 'sari-gallery', 'organization_id' => $sari->id, 'status' => 'published', 'published_at' => now()->subMinute()]);
        $this->getJson('/api/v1/galleries?organization_id='.$nur->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Nur gallery');
    }
}
