<?php

namespace Tests\Feature;

use App\Models\{OtpChallenge, Role, User};
use App\Services\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CitizenAdminEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unregistered_login_requires_registration_without_creating_an_account_or_challenge(): void
    {
        $before = User::count();
        $this->postJson('/api/v1/auth/citizen/login', ['mobile' => '09120005511'])->assertUnprocessable()->assertJsonValidationErrors('mobile');
        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->postJson('/api/v1/auth/citizen/login', ['mobile' => '123'])->assertUnprocessable();
    }

    public function test_registration_verification_subsequent_login_and_citizen_dashboard_work_with_revocable_tokens(): void
    {
        $payload = ['name' => 'شهروند نمونه', 'mobile' => '09120005511', 'national_code' => '0084575948'];
        $challenge = $this->postJson('/api/v1/auth/register', $payload)->assertOk()->json('data');
        $this->assertDatabaseMissing('users', ['mobile' => $payload['mobile']]);
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge['challenge_id'], 'code' => '000000'])->assertUnprocessable();
        $result = $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge['challenge_id'], 'code' => $challenge['dev_code']])->assertOk()->assertJsonPath('data.user.person_type', 'citizen')->assertJsonPath('data.user.portal_context.dashboard_path', '/citizen')->assertJsonPath('data.user.portal_context.login_path', '/login')->json('data');
        $this->assertNotNull(User::where('mobile', $payload['mobile'])->firstOrFail()->mobile_verified_at);
        $this->withToken($result['token'])->getJson('/api/v1/citizen/dashboard')->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($result['token'])->getJson('/api/v1/admin/dashboard')->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($result['token'])->postJson('/api/v1/auth/logout')->assertNoContent();
        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/citizen/login', ['mobile' => $payload['mobile']])->assertStatus(429);
        $this->travel(61)->seconds();
        $again = $this->postJson('/api/v1/auth/citizen/login', ['mobile' => $payload['mobile']])->assertOk()->json('data');
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $again['challenge_id'], 'code' => $again['dev_code']])->assertOk()->assertJsonPath('data.user.person_type', 'citizen');
        $this->assertSame(1, User::where('mobile', $payload['mobile'])->count());
    }

    public function test_wrong_audience_cannot_issue_a_token_and_valid_staff_receives_the_new_panel_paths(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $before = $admin->tokens()->count();
        $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'Testing-Only-Password42!', 'audience' => 'citizen'])->assertUnprocessable();
        $this->assertSame($before, $admin->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'Testing-Only-Password42!', 'audience' => 'staff'])->assertOk()->assertJsonPath('data.user.portal_context.dashboard_path', '/panel/admin')->assertJsonPath('data.user.portal_context.login_path', '/panel/admin/login');
        $citizen = User::create(['name' => 'Citizen', 'mobile' => '09120005522', 'national_code' => '0010350829', 'password' => 'Fixture-Password42!', 'person_type' => 'citizen', 'role_id' => Role::where('name', 'citizen')->value('id'), 'mobile_verified_at' => now(), 'is_active' => true]);
        $this->postJson('/api/v1/auth/login', ['login' => $citizen->mobile, 'password' => 'Fixture-Password42!', 'audience' => 'staff'])->assertUnprocessable();
        $this->assertSame(0, $citizen->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['login' => $citizen->mobile, 'password' => 'Fixture-Password42!', 'audience' => 'citizen'])->assertOk()->assertJsonPath('data.user.person_type', 'citizen');
    }

    public function test_citizen_otp_cannot_authenticate_a_staff_identity_even_if_a_challenge_already_exists(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $admin->update(['mobile' => '09120005533', 'national_code' => '0084575948', 'mobile_verified_at' => now()]);
        $this->postJson('/api/v1/auth/citizen/login', ['mobile' => $admin->mobile])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/register', ['name' => $admin->name, 'mobile' => $admin->mobile, 'national_code' => $admin->national_code])->assertUnprocessable();
        $challenge = new OtpChallenge;
        $challenge->id = (string) Str::uuid();
        $challenge->fill(['name' => $admin->name, 'mobile' => $admin->mobile, 'national_code' => $admin->national_code, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(2)]);
        $challenge->save();
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge->id, 'code' => '123456'])->assertUnprocessable();
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_unauthenticated_admin_reads_are_rejected_and_sms_delivery_failure_does_not_sign_in(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
        config(['portal.otp.driver' => 'http', 'portal.sms.endpoint' => null, 'portal.sms.token' => null]);
        $this->postJson('/api/v1/auth/register', ['name' => 'شهروند نمونه', 'mobile' => '09120005544', 'national_code' => '0084575948'])->assertStatus(503);
        $this->assertDatabaseMissing('users', ['mobile' => '09120005544']);
        $this->assertNotNull(OtpChallenge::where('mobile', '09120005544')->firstOrFail()->consumed_at);
    }
}
