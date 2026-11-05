<?php

namespace Tests\Feature;

use App\Models\{Category, Media, Member, Organization, Role, Setting, User};
use App\Services\{MemberDirectory, PortalView};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, File, Http};
use Tests\TestCase;

final class MemberDirectoryTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();$this->seed(DatabaseSeeder::class);
    }

    public function test_import_preserves_existing_people_accounts_county_content_and_later_edits(): void
    {
        $root=Organization::where('type','governorate')->firstOrFail();
        $root->update(['phone'=>'EXISTING-PHONE']);
        $county=Organization::where('slug','county-amol')->firstOrFail();
        $foreign=Member::create(['organization_id'=>$county->id,'name'=>'County fixture','kind'=>'county_governor','position'=>'Existing governor']);
        $governor=Member::create(['organization_id'=>$root->id,'name'=>'Current provincial governor','kind'=>'governor','position'=>'استاندار','is_current'=>true]);
        $existing=Member::create(['organization_id'=>$root->id,'name'=>'محمد ابراهیم تولایی','kind'=>'manager','position'=>'Existing detailed position','phone'=>'01112345678']);
        $accounts=User::pluck('password','id')->all();$roles=Role::pluck('permissions','id')->all();
        DB::enableQueryLog();
        $result=app(MemberDirectory::class)->import();
        $queries=count(DB::getQueryLog());DB::disableQueryLog();DB::flushQueryLog();
        $this->assertLessThan(40,$queries,'The directory import must batch its database requests so remote databases do not stall startup.');
        $this->assertSame(197,$result['created']);$this->assertSame(1,$result['existing']);
        $this->assertSame([2=>4,1=>24,3=>148,4=>22],$result['groups']);
        $this->assertSame('Existing detailed position',$existing->fresh()->position);
        $this->assertSame('01112345678',$existing->fresh()->phone);
        $this->assertSame($foreign->organization_id,$foreign->fresh()->organization_id);
        $this->assertSame($governor->id,Member::where('kind','governor')->where('is_current',true)->firstOrFail()->id);
        $staff=Member::where('name','سید جمال هاشمی')->firstOrFail();$staff->update(['name'=>'Edited directory entry']);
        $removed=Member::where('name','فرهاد بیگی')->firstOrFail();$removed->delete();
        $second=app(MemberDirectory::class)->import();
        $this->assertTrue($second['already_imported']);$this->assertSame(200,Member::withTrashed()->count());
        $this->assertSame('Edited directory entry',$staff->fresh()->name);
        $this->assertSoftDeleted('members',['id'=>$removed->id]);
        $this->assertSame($accounts,User::pluck('password','id')->all());$this->assertSame($roles,Role::pluck('permissions','id')->all());
        $this->assertSame('EXISTING-PHONE',$root->fresh()->phone);
        $this->assertSame(22,Member::where('kind','county_governor')->where('organization_id',$root->id)->whereNull('county_id')->count());
    }

    public function test_all_groups_are_visible_and_the_148_person_group_is_paginated_before_rendering(): void
    {
        app(MemberDirectory::class)->import();
        foreach(MemberDirectory::GROUPS as $key=>$group){
            $this->getJson('/api/v1/members?member_group='.$key)->assertOk()->assertJsonPath('meta.total',$group['count']);
            $this->get('/managers?group='.$key)->assertOk()->assertSee($group['names'][0]??match($key){2=>'محمد ابراهیم تولایی',1=>'سید جمال هاشمی',3=>'جابر حسن زاده',4=>'ابوذر قربانی ارات بنی'});
        }
        $ids=[];
        for($page=1;$page<=7;$page++){
            $result=$this->getJson('/api/v1/members?member_group=3&per_page=24&page='.$page)->assertOk()->assertJsonPath('meta.total',148)->assertJsonPath('meta.last_page',7);
            $ids=[...$ids,...array_column($result->json('data'),'id')];
        }
        $this->assertCount(148,array_unique($ids));
        $this->get('/section/menu-62?page=7')->assertOk()->assertSee('فرهاد بیگی')->assertSee('aria-label="صفحه‌های مدیران"',false);
        $this->get('/deputies')->assertOk()->assertSee('محمد ابراهیم تولایی')->assertDontSee('جابر حسن زاده');
        $members=collect($this->getJson('/api/v1/home')->assertOk()->json('data.members'))->flatten(1)->all();
        foreach(MemberDirectory::GROUPS as $key=>$group)$this->assertCount($group['count'],app(PortalView::class)->membersForGroup($members,(string)$key));
        $this->get('/')->assertOk()->assertSee('جابر حسن زاده')->assertSee('ابوذر قربانی ارات بنی');
        $this->getJson('/api/v1/members?member_group=invalid')->assertUnprocessable();
    }

    public function test_profiles_cover_all_groups_without_exposing_other_county_workspaces(): void
    {
        app(MemberDirectory::class)->import();
        $county=Organization::where('slug','county-amol')->firstOrFail();
        $actor=User::create(['name'=>'County PR','username'=>'directory_county_pr','password'=>'County-Fixture42!',
            'organization_id'=>$county->id,'role_id'=>Role::where('name','county_public_relations')->value('id'),
            'person_type'=>'staff','access_scope'=>'organization','is_active'=>true]);
        foreach(['محمد ابراهیم تولایی','فرهاد بیگی','ابوذر قربانی ارات بنی'] as $name){
            $person=Member::where('name',$name)->firstOrFail();
            $this->get('/managers?person='.$person->id)->assertOk()->assertSee($name);
            $this->get('/amol/managers?person='.$person->id)->assertNotFound();
            $this->actingAs($actor,'web')->getJson('/web-api/v1/admin/members/'.$person->id)->assertNotFound();
        }
    }

    public function test_uploaded_portraits_override_the_shared_image_and_existing_admin_crud_still_works(): void
    {
        app(MemberDirectory::class)->import();
        $person=Member::where('name','جابر حسن زاده')->firstOrFail();
        $this->assertSame(MemberDirectory::portraitUrl(),$person->image_url);
        $admin=User::where('username','admin')->firstOrFail();
        $image=Media::create(['user_id'=>$admin->id,'organization_id'=>$admin->organization_id,'disk'=>'public','path'=>'fixture/person.webp',
            'original_name'=>'person.webp','mime'=>'image/webp','size'=>30,'visibility'=>'public']);
        $this->actingAs($admin,'web')->patchJson('/web-api/v1/admin/members/'.$person->id,['media_id'=>$image->id,'position'=>'Edited precise position'])->assertOk();
        $person->refresh()->load(['image','category']);
        $this->assertNull($person->image_url);$this->assertSame($image->url,app(PortalView::class)->image($person->toArray()));
        $this->get('/managers?person='.$person->id)->assertOk()->assertSee('Edited precise position')->assertSee($image->url,false);
    }

    public function test_disabled_groups_do_not_get_reactivated_or_leave_a_partial_import(): void
    {
        Category::create(['type'=>'member','locale'=>'fa','slug'=>'official-member-menu-61','name'=>'Disabled group','is_active'=>false]);
        try{app(MemberDirectory::class)->import();$this->fail('The import should stop for a disabled group.');}catch(\RuntimeException $e){$this->assertStringContainsString('disabled',$e->getMessage());}
        $this->assertDatabaseCount('members',0);$this->assertDatabaseCount('categories',1);
        $this->assertDatabaseMissing('settings',['key'=>MemberDirectory::IMPORT_KEY]);
    }

    public function test_failed_photo_download_does_not_block_names_and_successful_download_uses_a_local_image(): void
    {
        $original=public_path();$temporary=sys_get_temp_dir().'/member-directory-'.bin2hex(random_bytes(8));
        $jpeg=file_get_contents($original.'/design/shared/29086cb1e1d6be9abdec.jpg');
        $this->app->usePublicPath($temporary);
        try{
            Http::fake([MemberDirectory::PHOTO_URL=>Http::sequence()->push('',504)->push('<html>not a photo</html>',200)->push($jpeg,200,['Content-Type'=>'image/jpeg'])]);
            $this->artisan('portal:import-member-directory')->assertSuccessful();
            $this->assertDatabaseCount('members',198);$this->assertSame(MemberDirectory::PHOTO_URL,MemberDirectory::portraitUrl());
            $this->artisan('portal:import-member-directory')->assertSuccessful();
            Http::assertSentCount(1);
            $this->assertFalse(app(MemberDirectory::class)->downloadPortrait());
            $this->assertTrue(app(MemberDirectory::class)->downloadPortrait());
            $this->assertSame($jpeg,file_get_contents($temporary.'/'.MemberDirectory::PHOTO_PATH));
            $this->assertSame('/'.MemberDirectory::PHOTO_PATH,MemberDirectory::portraitUrl());
        }finally{File::deleteDirectory($temporary);$this->app->usePublicPath($original);}
    }
}
