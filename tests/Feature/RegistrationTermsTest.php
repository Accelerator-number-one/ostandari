<?php

namespace Tests\Feature;

use App\Models\{AuditLog, OtpChallenge, Role, User};
use App\Services\RegistrationTerms;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegistrationTermsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'name' => 'شهروند آزمایشی', 'mobile' => '09120007711', 'national_code' => '0084575948',
            'terms_accepted' => true, 'terms_version' => RegistrationTerms::VERSION,
            'terms_document_sha256' => RegistrationTerms::documentHash(),
        ], $changes);
    }

    private function acceptanceCount(): int
    {
        return AuditLog::where('action', 'auth.registration_terms_accepted')->count();
    }

    public function test_registration_requires_explicit_consent_on_both_registration_entry_points_before_creating_a_challenge(): void
    {
        $before = User::count();
        foreach (['register', 'otp/request'] as $path) {
            foreach ([null, false, 'false', 'no', 0] as $value) {
                $this->postJson('/api/v1/auth/'.$path, $this->payload(['terms_accepted' => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors('terms_accepted');
                $this->assertDatabaseCount('otp_challenges', 0);
                $this->travel(61)->seconds();
            }
        }
        $this->assertSame($before, User::count());
        $this->assertSame(0, $this->acceptanceCount());
    }

    public function test_missing_or_old_terms_version_and_document_hash_are_not_accepted(): void
    {
        foreach ([['terms_version' => null], ['terms_version' => '0.9'], ['terms_document_sha256' => null], ['terms_document_sha256' => str_repeat('0', 64)]] as $change) {
            $this->postJson('/api/v1/auth/register', $this->payload($change))
                ->assertUnprocessable()->assertJsonValidationErrors(array_key_first($change));
            $this->assertDatabaseCount('otp_challenges', 0);
            $this->travel(61)->seconds();
        }
    }

    public function test_verified_registration_records_the_exact_terms_version_document_and_server_time_once(): void
    {
        $this->freezeTime();
        $result = $this->postJson('/api/v1/auth/register', $this->payload(['terms_accepted_at' => '1999-01-01T00:00:00Z']))
            ->assertOk()->json('data');
        $challenge = OtpChallenge::findOrFail($result['challenge_id']);
        $this->assertSame(RegistrationTerms::VERSION, $challenge->registration_terms['version']);
        $this->assertSame(RegistrationTerms::documentHash(), $challenge->registration_terms['document_sha256']);
        $this->assertSame(now()->toIso8601String(), $challenge->registration_terms['accepted_at']);
        $this->assertDatabaseMissing('users', ['mobile' => '09120007711']);
        $this->assertSame(0, $this->acceptanceCount());
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge->id, 'code' => '000000'])->assertUnprocessable();
        $this->assertSame(0, $this->acceptanceCount());
        $verified = $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge->id, 'code' => $result['dev_code']])
            ->assertOk()->assertJsonPath('data.user.portal_context.dashboard_path', '/citizen')->json('data');
        $log = AuditLog::where('action', 'auth.registration_terms_accepted')->sole();
        $this->assertSame($verified['user']['id'], $log->user_id);
        $this->assertSame($log->user_id, $log->subject_id);
        $this->assertSame('User', $log->subject_type);
        $this->assertSame($challenge->registration_terms, $log->changes);
        $this->assertArrayNotHasKey('national_code', $log->changes);
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $challenge->id, 'code' => $result['dev_code']])->assertUnprocessable();
        $this->assertSame(1, $this->acceptanceCount());
    }

    public function test_a_new_account_cannot_be_created_from_a_challenge_with_missing_or_stale_consent(): void
    {
        foreach ([null, array_replace(RegistrationTerms::acceptance(), ['version' => '0.9']), array_replace(RegistrationTerms::acceptance(), ['document_sha256' => str_repeat('0', 64)])] as $acceptance) {
            $result = $this->postJson('/api/v1/auth/register', $this->payload())->assertOk()->json('data');
            OtpChallenge::findOrFail($result['challenge_id'])->update(['registration_terms' => $acceptance]);
            $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $result['challenge_id'], 'code' => $result['dev_code']])->assertUnprocessable();
            $this->assertDatabaseMissing('users', ['mobile' => '09120007711']);
            $this->assertSame(0, $this->acceptanceCount());
            $this->travel(61)->seconds();
        }
    }

    public function test_citizen_login_for_an_existing_verified_user_does_not_require_or_invent_new_consent(): void
    {
        User::create(['name' => 'Existing Citizen', 'mobile' => '09120007711', 'national_code' => '0084575948', 'person_type' => 'citizen', 'role_id' => Role::where('name', 'citizen')->value('id'), 'mobile_verified_at' => now(), 'is_active' => true]);
        $result = $this->postJson('/api/v1/auth/citizen/login', ['mobile' => '09120007711'])->assertOk()->json('data');
        $this->assertNull(OtpChallenge::findOrFail($result['challenge_id'])->registration_terms);
        $this->postJson('/api/v1/auth/otp/verify', ['challenge_id' => $result['challenge_id'], 'code' => $result['dev_code']])->assertOk();
        $this->assertSame(0, $this->acceptanceCount());
    }

    public function test_sms_failure_cannot_create_a_completed_acceptance_record(): void
    {
        config(['portal.otp.driver' => 'http', 'portal.sms.endpoint' => null, 'portal.sms.token' => null]);
        $this->postJson('/api/v1/auth/register', $this->payload())->assertStatus(503);
        $this->assertNotNull(OtpChallenge::where('mobile', '09120007711')->sole()->consumed_at);
        $this->assertDatabaseMissing('users', ['mobile' => '09120007711']);
        $this->assertSame(0, $this->acceptanceCount());
    }

    public function test_the_complete_versioned_document_is_deployed_with_all_articles_and_the_original_effective_date_placeholder(): void
    {
        $document = json_decode(file_get_contents(resource_path('legal/citizen-registration-terms-1.0.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(RegistrationTerms::VERSION, $document['version']);
        $this->assertSame('[تاریخ ابلاغ]', $document['effectiveDate']);
        $this->assertCount(17, $document['articles']);
        $this->assertSame([7,7,6,7,7,8,7,6,8,8,11,5,5,4,5,8,4], array_map(fn ($article) => count($article['clauses']), $document['articles']));
        $this->assertCount(3, $document['introduction']);
        $this->assertCount(4, $document['notice']['paragraphs']);
    }
}
