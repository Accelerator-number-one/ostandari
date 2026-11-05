<?php

namespace Tests\Feature;

use App\Models\{County, Organization, Role, Ticket, User};
use App\Services\PortalView;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CitizenReportRoutingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function asWeb(User $user): self
    {
        $this->app['auth']->forgetGuards();
        return $this->actingAs($user, 'web');
    }

    private function citizen(string $name = 'شهروند آزمایشی'): User
    {
        return User::create(['name'=>$name,'person_type'=>'citizen','role_id'=>Role::where('name','citizen')->value('id'),'is_active'=>true]);
    }

    private function staff(Organization $org): User
    {
        return User::create(['name'=>'روابط عمومی آزمایشی '.$org->name,'person_type'=>'staff','organization_id'=>$org->id,'access_scope'=>'organization',
            'role_id'=>Role::where('name',$org->type==='county'?'county_public_relations':'province_public_relations')->value('id'),'is_active'=>true]);
    }

    private function report(County $county, array $extra = []): array
    {
        return $extra + ['type'=>'report','county_id'=>$county->id,'category'=>'roads','subject'=>'گزارش آزمایشی '.$county->name,
            'body'=>'این گزارش آزمایشی فقط برای بررسی رسیدگی در محیط تست ثبت شده است.','urgency'=>'high'];
    }

    public function test_reports_from_every_county_reach_only_the_correct_county_panel_and_central_oversight(): void
    {
        $owner=$this->citizen();
        $anotherCitizen=$this->citizen('شهروند دیگر');
        $root=Organization::where('type','governorate')->firstOrFail();
        $admin=User::where('username','admin')->firstOrFail();
        $counties=County::active()->orderBy('id')->get();
        $this->assertNotEmpty($counties);
        $operators=[];
        foreach($counties as $county) {
            $org=Organization::active()->where('type','county')->where('county_id',$county->id)->sole();
            $operators[$county->id]=$this->staff($org);
        }
        foreach($counties as $county) {
            $operator=$operators[$county->id];
            $other=$counties->first(fn($item)=>$item->id!==$county->id);
            $otherOperator=$operators[$other->id];
            $this->asWeb($owner);
            $record=$this->postJson('/web-api/v1/citizen/tickets',$this->report($county,['organization_id'=>$root->id,'assigned_to'=>$otherOperator->id]))
                ->assertCreated()->assertJsonPath('data.organization_id',$operator->organization_id)->assertJsonPath('data.user_id',$owner->id)
                ->assertJsonPath('data.status','pending')->assertJsonPath('data.assigned_to',null)->json('data');
            $ref=$record['reference'];
            $this->getJson('/web-api/v1/citizen/tickets/'.$ref)->assertOk()->assertJsonPath('data.body',$record['body']);
            $this->asWeb($operator);
            $this->getJson('/web-api/v1/admin/reports?q='.$ref)->assertOk()->assertJsonPath('summary.total',1)->assertJsonPath('data.0.reference',$ref);
            $this->getJson('/web-api/v1/admin/reports/'.$ref)->assertOk()->assertJsonPath('data.body',$record['body']);
            $this->getJson('/web-api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.recent_reports.0.reference',$ref);
            $slug=app(PortalView::class)->county($county->slug)['slug'];
            $otherSlug=app(PortalView::class)->county($other->slug)['slug'];
            $this->get('/county-admin/'.$slug.'/reports/'.$ref.'/edit')->assertOk()->assertSee('data-reference="'.$ref.'"',false);
            $this->get('/county-admin/'.$otherSlug.'/reports/'.$ref.'/edit')->assertForbidden();
            $this->asWeb($otherOperator);
            $this->getJson('/web-api/v1/admin/reports?q='.$ref)->assertOk()->assertJsonPath('summary.total',0)->assertJsonCount(0,'data');
            $this->getJson('/web-api/v1/admin/reports/'.$ref)->assertNotFound();
            $this->postJson('/web-api/v1/admin/reports/'.$ref.'/messages',['body'=>'پاسخ خارج از محدوده'])->assertNotFound();
            $this->asWeb($anotherCitizen);
            $this->getJson('/web-api/v1/citizen/tickets/'.$ref)->assertNotFound();
            $this->getJson('/web-api/v1/admin/reports/'.$ref)->assertForbidden();
            $this->asWeb($admin);
            $this->getJson('/web-api/v1/admin/reports/'.$ref)->assertOk()->assertJsonPath('data.organization_id',$operator->organization_id);
        }
        $this->assertDatabaseCount('tickets',$counties->count());
    }

    public function test_direct_governor_messages_go_to_the_province_and_invalid_destinations_do_not_create_tickets(): void
    {
        $root=Organization::active()->where('type','governorate')->firstOrFail();
        $county=County::active()->firstOrFail();
        $countyOrg=Organization::active()->where('type','county')->where('county_id',$county->id)->firstOrFail();
        $citizen=$this->citizen();
        $province=$this->staff($root);
        $countyStaff=$this->staff($countyOrg);
        $this->asWeb($citizen);
        $ref=$this->postJson('/web-api/v1/citizen/tickets',['type'=>'governor','subject'=>'پیام مستقیم آزمایشی','body'=>'متن آزمایشی پیام مستقیم شهروند به استاندار.'])
            ->assertCreated()->assertJsonPath('data.organization_id',$root->id)->json('data.reference');
        $this->asWeb($province);
        $this->getJson('/web-api/v1/admin/reports/'.$ref)->assertOk()->assertJsonPath('data.type','governor');
        $this->asWeb($countyStaff);
        $this->getJson('/web-api/v1/admin/reports/'.$ref)->assertNotFound();
        $this->asWeb($citizen);
        foreach([['county_id'=>null],['county_id'=>999999],['category'=>null],['body'=>'']] as $invalid)
            $this->postJson('/web-api/v1/citizen/tickets',$this->report($county,$invalid))->assertUnprocessable();
        $countyOrg->update(['is_active'=>false]);
        $this->postJson('/web-api/v1/citizen/tickets',$this->report($county))->assertUnprocessable();
        $this->assertDatabaseCount('tickets',1);
    }

    public function test_county_response_status_private_attachments_and_internal_notes_reach_the_right_participants(): void
    {
        $citizen=$this->citizen();
        [$org,$otherOrg]=Organization::active()->where('type','county')->with('county')->limit(2)->get()->all();
        $operator=$this->staff($org);
        $other=$this->staff($otherOrg);
        $this->asWeb($citizen);
        $file=$this->post('/web-api/v1/media',['visibility'=>'private','file'=>UploadedFile::fake()->image('report-evidence.jpg',24,32)],['Accept'=>'application/json'])
            ->assertCreated()->assertJsonPath('data.url',null)->json('data.id');
        $ref=$this->postJson('/web-api/v1/citizen/tickets',$this->report($org->county,['media_ids'=>[$file]]))->assertCreated()->json('data.reference');
        Storage::fake('local'); // Another application instance has none of the uploader's temporary files.
        $this->asWeb($operator);
        $this->get('/web-api/v1/media/'.$file.'/download')->assertOk();
        $this->postJson('/web-api/v1/admin/reports/'.$ref.'/messages',['body'=>'یادداشت داخلی آزمایشی','is_internal'=>true])->assertCreated();
        $this->patchJson('/web-api/v1/admin/reports/'.$ref,['status'=>'review','reply'=>'پاسخ عمومی آزمایشی برای همان شهروند'])->assertOk();
        $this->patchJson('/web-api/v1/admin/reports/'.$ref,['status'=>'resolved','note'=>'جزئیات داخلی رسیدگی'])->assertOk();
        $this->asWeb($other);
        $this->get('/web-api/v1/media/'.$file.'/download')->assertNotFound();
        $this->asWeb($citizen);
        $this->getJson('/web-api/v1/citizen/tickets/'.$ref)->assertOk()->assertJsonPath('data.status','resolved')->assertJsonCount(1,'data.messages')
            ->assertJsonPath('data.messages.0.body','پاسخ عمومی آزمایشی برای همان شهروند')->assertJsonCount(1,'data.media')
            ->assertJsonMissing(['body'=>'یادداشت داخلی آزمایشی'])->assertJsonMissing(['note'=>'جزئیات داخلی رسیدگی']);
        $notifications=$this->getJson('/web-api/v1/citizen/notifications')->assertOk()->json('data');
        $this->assertTrue(collect($notifications)->every(fn($notice)=>($notice['data']['reference']??null)===$ref));
        $this->assertCount(4,$notifications);
    }
}
