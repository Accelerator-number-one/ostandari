<?php

namespace Tests\Feature;

use App\Models\{Content, Media, MenuItem, Organization, Role, Setting, User};
use App\Services\{CountyPortal, PortalView, Workspace};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CountyWorkspaceTest extends TestCase
{
    use DatabaseMigrations;
    private User $amol;
    private User $nur;
    private User $province;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        foreach (['amol','nur'] as $slug) {
            $org=Organization::where('type','county')->whereHas('county',fn($q)=>$q->where('slug',$slug))->firstOrFail();
            $this->$slug=User::create(['name'=>$slug.' PR','username'=>'workspace_'.$slug,'password'=>'County-Fixture42!',
                'organization_id'=>$org->id,'role_id'=>Role::where('name','county_public_relations')->value('id'),
                'person_type'=>'staff','access_scope'=>'organization','is_active'=>true]);
        }
        $this->province=User::create(['name'=>'Province PR','username'=>'workspace_province','password'=>'County-Fixture42!',
            'organization_id'=>Organization::where('type','governorate')->value('id'),
            'role_id'=>Role::where('name','province_public_relations')->value('id'),'person_type'=>'staff','access_scope'=>'organization','is_active'=>true]);
    }

    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user,'web');
    }

    private function image(User $owner): Media
    {
        return Media::create(['user_id'=>$owner->id,'organization_id'=>$owner->organization_id,'disk'=>'public',
            'path'=>'fixture/'.Str::uuid().'.webp','original_name'=>'fixture.webp','mime'=>'image/webp','size'=>30,'visibility'=>'public']);
    }

    public function test_county_routes_keep_unauthenticated_users_and_every_other_workspace_out(): void
    {
        $this->get('/county-admin/amol/news')->assertRedirect('/county-admin/amol/login?error=auth_required&return=%2Fcounty-admin%2Famol%2Fnews');
        $this->get('/county-admin/not-a-county')->assertNotFound();
        foreach ([$this->province,User::where('username','admin')->firstOrFail(),$this->nur] as $foreign) {
            $this->as($foreign);
            foreach (['','/news','/news/new','/portal','/account'] as $path) $this->get('/county-admin/amol'.$path)->assertForbidden();
        }
        $this->as($this->amol);
        $this->get('/panel/admin/news?q=test')->assertRedirect('/county-admin/amol/news?q=test');
        $this->get('/county-admin/nur/news')->assertForbidden();
        $this->get('/county-admin/amol/settings')->assertForbidden();
        $this->get('/county-admin/amol/portal')->assertOk()->assertSee('data-county-portal-form',false);
        $this->amol->organization->update(['is_active'=>false]);$this->amol->unsetRelation('organization');
        $this->get('/county-admin/amol')->assertForbidden();
    }

    public function test_every_available_county_module_and_create_form_renders_with_county_navigation(): void
    {
        $this->as($this->amol);
        $ui=app(PortalView::class);
        $this->get('/county-admin/amol')->assertOk()->assertSee('داشبورد فرمانداری آمل')
            ->assertDontSee('مدیریت محتوای سازمان و پیگیری خدمات مردمی')
            ->assertDontSee('خبرهای همین سازمان')->assertDontSee('استانداری و سازمان‌ها');
        foreach ($ui->data('frontend')['modules'] as $module) {
            if(!$ui->moduleAllowed($module,$this->amol))continue;
            $url=$ui->adminHref($module['path'],Workspace::context($this->amol));
            $response=$this->get($url)->assertOk();
            if($module['page']??false){
                $document=new \DOMDocument;@$document->loadHTML($response->getContent());
                foreach ($document->getElementsByTagName('a') as $anchor) $this->assertFalse(str_starts_with($anchor->getAttribute('href'),'/panel/admin'),$url.' retained central link '.$anchor->getAttribute('href'));
                if($module['canCreate']&&!in_array($module['resource'],['organizations','counties'],true)){
                    $form=$this->get($url.'/new')->assertOk()->assertDontSee('data-editor-field="organization_id"',false);
                    $fields=new \DOMDocument;@$fields->loadHTML($form->getContent());
                    foreach ((new \DOMXPath($fields))->query('//label|//th|//summary') as $label)$this->assertStringNotContainsString('سازمان',$label->textContent,$url.' retained a generic organization label');
                }
            }
        }
        $this->get('/county-admin/amol/pages/new')->assertOk()->assertSee('data-field="portal_path"',false);
        $this->as(User::where('username','admin')->firstOrFail());
        $this->get('/panel/admin/news/new')->assertOk()->assertSee('data-editor-field="organization_id"',false);
    }

    public function test_provincial_and_non_pr_accounts_cannot_use_county_login_or_settings(): void
    {
        $before=\DB::table('personal_access_tokens')->count();
        $this->postJson('/session/login',['login'=>$this->province->username,'password'=>'County-Fixture42!','audience'=>'staff','county_slug'=>'amol'])->assertForbidden();
        $this->assertSame($before,\DB::table('personal_access_tokens')->count());
        $this->as($this->province);
        $this->getJson('/web-api/v1/admin/county-portal')->assertForbidden();
        $this->putJson('/web-api/v1/admin/county-portal',['title'=>'Forbidden'])->assertForbidden();
        $role=Role::create(['name'=>'county_operator_fixture','label'=>'Operator','permissions'=>Workspace::PUBLIC_RELATIONS_PERMISSIONS]);
        $this->amol->update(['role_id'=>$role->id]);$this->amol->unsetRelation('role');$this->as($this->amol);
        $this->get('/county-admin/amol')->assertForbidden();
        $this->getJson('/web-api/v1/admin/county-portal')->assertForbidden();
    }

    public function test_county_can_change_its_own_public_identity_sections_and_images_only(): void
    {
        $this->as($this->amol);
        $original=Setting::where('key','site.name')->value('value');
        $foreignDescription=$this->nur->organization->county->description;
        $logo=$this->image($this->amol);$banner=$this->image($this->amol);$slogan=$this->image($this->amol);
        $this->putJson('/web-api/v1/admin/county-portal',['title'=>'پرتال ویژه آمل','about_body'=>'<h2>معرفی اختصاصی آمل</h2><script>alert(1)</script>',
            'county_description'=>'معرفی کوتاه آمل','phone'=>'01112345678','logo_media_id'=>$logo->id,'banner_media_id'=>$banner->id,
            'slogan_media_id'=>$slogan->id,'slogan_text'=>'','section_gallery'=>false,'title_news'=>'تازه‌های آمل'])
            ->assertOk()->assertJsonPath('data.logo_media_id',$logo->id)->assertJsonPath('data.section_gallery',false)->assertJsonPath('data.slogan_text','');
        $this->get('/amol')->assertOk()->assertSee('پرتال ویژه آمل')->assertSee($logo->url,false)->assertSee($banner->url,false)->assertSee($slogan->url,false)->assertSee('تازه‌های آمل')->assertDontSee('portal-office-gallery',false);
        $this->get('/amol/about')->assertOk()->assertSee('معرفی اختصاصی آمل')->assertDontSee('alert(1)',false);
        $this->get('/nur')->assertOk()->assertDontSee('پرتال ویژه آمل')->assertDontSee($logo->url,false);
        $this->assertSame($original,Setting::where('key','site.name')->value('value'));
        $this->assertSame($foreignDescription,$this->nur->organization->county->fresh()->description);
        $this->deleteJson('/web-api/v1/media/'.$logo->id)->assertStatus(409);
        $this->putJson('/web-api/v1/admin/county-portal',['logo_media_id'=>null])->assertOk();
        $this->deleteJson('/web-api/v1/media/'.$logo->id)->assertNoContent();
    }

    public function test_county_settings_reject_foreign_images_identity_changes_and_foreign_inline_images(): void
    {
        $this->as($this->amol);$foreign=$this->image($this->nur);
        foreach (array_keys(CountyPortal::IMAGES) as $field)$this->putJson('/web-api/v1/admin/county-portal',[$field=>$foreign->id])->assertForbidden();
        foreach (['organization_id'=>$this->nur->organization_id,'county_id'=>$this->nur->organization->county_id,'slug'=>'nur','access_scope'=>'all'] as $key=>$value)
            $this->putJson('/web-api/v1/admin/county-portal',[$key=>$value])->assertForbidden();
        $this->putJson('/web-api/v1/admin/county-portal',['about_body'=>'<img src="'.$foreign->url.'">'])->assertForbidden();
        $this->assertDatabaseMissing('settings',['key'=>CountyPortal::key($this->amol->organization)]);
        $this->patchJson('/web-api/v1/admin/contents/999999',['organization_id'=>$this->nur->organization_id])->assertNotFound();
    }

    public function test_county_menu_setup_is_idempotent_and_empty_or_disabled_menus_do_not_restore_old_links(): void
    {
        $this->as($this->amol);
        $id=$this->postJson('/web-api/v1/admin/county-portal/menus',['group'=>'quick'])->assertOk()->json('data.id');
        $count=MenuItem::where('menu_id',$id)->count();
        $this->postJson('/web-api/v1/admin/county-portal/menus',['group'=>'quick'])->assertOk()->assertJsonPath('data.id',$id);
        $this->assertSame($count,MenuItem::where('menu_id',$id)->count());
        $item=MenuItem::where('menu_id',$id)->firstOrFail();
        $this->patchJson('/web-api/v1/admin/menu-items/'.$item->id,['title'=>'دسترسی ویژه آمل'])->assertOk();
        $this->get('/amol')->assertOk()->assertSee('دسترسی ویژه آمل');
        $this->get('/nur')->assertOk()->assertDontSee('دسترسی ویژه آمل');
        $this->as($this->nur);$this->patchJson('/web-api/v1/admin/menu-items/'.$item->id,['title'=>'Forged'])->assertNotFound();
        $this->as($this->amol);$this->patchJson('/web-api/v1/admin/menus/'.$id,['is_active'=>false])->assertOk();
        $this->get('/amol')->assertOk()->assertDontSee('دسترسی ویژه آمل')->assertDontSee('میز خدمت الکترونیکی');
        $main=$this->postJson('/web-api/v1/admin/county-portal/menus',['group'=>'main'])->assertOk()->json('data.id');
        $this->assertGreaterThan(10,MenuItem::where('menu_id',$main)->count());
        $this->get('/amol')->assertOk();
    }

    public function test_county_news_automatically_reaches_the_provincial_feed_and_follows_publication_and_deletion(): void
    {
        $this->as($this->amol);
        $id=$this->postJson('/web-api/v1/admin/contents',['type'=>'news','title'=>'گزارش منتشرشده فرمانداری آمل','body'=>'<p>متن خبر</p>','status'=>'draft','news_scope'=>'province'])
            ->assertCreated()->assertJsonPath('data.news_scope','governorates')->json('data.id');
        $this->assertSame([],$this->getJson('/api/v1/home')->assertOk()->json('data.news_feeds.governorates'));
        $this->patchJson('/web-api/v1/admin/contents/'.$id,['status'=>'published'])->assertOk();
        $home=$this->getJson('/api/v1/home')->assertOk()->json('data');
        $this->assertSame([$id],array_column($home['news_feeds']['governorates'],'id'));
        $this->assertSame([],array_column($home['news_feeds']['province'],'id'));
        $this->get('/')->assertOk()->assertSee('/amol/content/'.$id,false);
        $this->get('/amol/content/'.$id)->assertOk()->assertSee('گزارش منتشرشده فرمانداری آمل');
        $this->get('/nur/content/'.$id)->assertNotFound();
        // Legacy imports cannot hide a county article by assigning a provincial group.
        Content::findOrFail($id)->update(['metadata'=>['news_scope'=>'province']]);
        $this->assertSame([$id],$this->getJson('/api/v1/home')->json('data.news_feeds.governorates.*.id'));
        $this->deleteJson('/web-api/v1/admin/contents/'.$id)->assertNoContent();
        $this->assertSame([],$this->getJson('/api/v1/home')->json('data.news_feeds.governorates'));
        $admin=User::where('username','admin')->firstOrFail();
        $curated=Content::create(['type'=>'news','title'=>'Curated governorates report','slug'=>'curated-governorates','body'=>'Published report',
            'organization_id'=>$admin->organization_id,'author_id'=>$admin->id,'status'=>'published','published_at'=>now()->subMinute(),'metadata'=>['news_scope'=>'governorates']]);
        $this->assertSame([$curated->id],$this->getJson('/api/v1/home')->json('data.news_feeds.governorates.*.id'));
    }

    public function test_county_page_bindings_are_editable_and_never_replace_another_portal_page(): void
    {
        $ids=[];
        foreach (['amol','nur'] as $slug) {
            $this->as($this->$slug);
            $ids[$slug]=$this->postJson('/web-api/v1/admin/contents',['type'=>'page','title'=>'Information '.$slug,'body'=>'<p>Details '.$slug.'</p>',
                'status'=>'published','portal_path'=>'/information'])->assertCreated()->assertJsonPath('data.portal_path','/information')->json('data.id');
        }
        $this->get('/amol/information')->assertOk()->assertSee('Details amol')->assertDontSee('Details nur');
        $this->get('/nur/information')->assertOk()->assertSee('Details nur')->assertDontSee('Details amol');
        $this->as($this->amol);
        $this->postJson('/web-api/v1/admin/contents',['type'=>'page','title'=>'Duplicate information','body'=>'Details','portal_path'=>'/information'])->assertUnprocessable();
        $this->patchJson('/web-api/v1/admin/contents/'.$ids['nur'],['body'=>'Forged'])->assertNotFound();
        $this->patchJson('/web-api/v1/admin/contents/'.$ids['amol'],['body'=>'<p>Updated amol</p>'])->assertOk();
        $this->get('/amol/information')->assertOk()->assertSee('Updated amol');
        $this->patchJson('/web-api/v1/admin/contents/'.$ids['amol'],['portal_path'=>null])->assertOk()->assertJsonPath('data.portal_path',null);
        $this->get('/amol/information')->assertOk()->assertDontSee('Updated amol');
    }
}
