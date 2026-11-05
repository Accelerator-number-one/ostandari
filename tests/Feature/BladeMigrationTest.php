<?php

namespace Tests\Feature;

use App\Models\{Content, DynamicForm, Organization, Role, Setting, User};
use App\Services\PortalView;
use App\Services\RegistrationTerms;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OfficialPublicSiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BladeMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    public function test_public_pages_and_all_county_portals_render_as_blade(): void
    {
        foreach (['/','/amol','/sari','/counties','/news','/gallery','/services','/links','/managers','/governors','/faq','/polls','/forms','/contact','/about','/install','/download','/search','/transparency','/sitemap','/forum','/regulations','/privacy','/login','/register','/panel/admin/login','/county-admin/amol/login'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('/_next/',false);
        }
        foreach(app(PortalView::class)->data('frontend')['counties'] as $county) $this->get('/'.$county['slug'])->assertOk()->assertSee($county['name']);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type','application/xml; charset=UTF-8');
        $this->get('/admin')->assertNotFound();
    }

    public function test_management_and_citizen_entry_are_protected(): void
    {
        $this->get('/panel/admin/news/new')->assertRedirect();
        $this->get('/citizen')->assertRedirect('/login?return=%2Fcitizen');
        $citizen=User::create(['name'=>'Citizen fixture','person_type'=>'citizen','is_active'=>true,'role_id'=>Role::where('name','citizen')->value('id')]);
        $this->actingAs($citizen,'web')->get('/panel/admin')->assertForbidden();
        $this->get('/citizen')->assertOk()->assertSee('portalPage',false);
    }

    public function test_native_login_establishes_session_without_exposing_or_retaining_bearer_token(): void
    {
        $response=$this->postJson('/session/login',['login'=>'admin','password'=>'Testing-Only-Password42!','audience'=>'staff'])->assertOk();
        $response->assertJsonMissingPath('data.token');
        $this->assertAuthenticatedAs(User::where('username','admin')->firstOrFail(),'web');
        $this->assertDatabaseCount('personal_access_tokens',0);
        $this->getJson('/web-api/v1/admin/dashboard')->assertOk();
        $this->get('/panel/admin/news/new')->assertOk()->assertSee('admin-record-form',false);
        $this->postJson('/session/logout')->assertOk();
        $this->assertGuest('web');
        $this->getJson('/web-api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_every_authorized_management_list_and_editor_renders(): void
    {
        $this->actingAs(User::where('username','admin')->firstOrFail(),'web');
        $this->get('/panel/admin')->assertOk();
        foreach(app(PortalView::class)->data('frontend')['modules'] as $module){
            $this->get($module['path'])->assertOk();
            if($module['canCreate'])$this->get($module['path'].'/new')->assertOk()->assertSee('admin-record-form',false);
        }
        $this->get('/panel/admin/account')->assertOk();
        $this->get('/panel/admin/logout')->assertOk();
    }

    public function test_provincial_pr_year_logo_uses_native_session_and_retains_read_only_boundary(): void
    {
        $user=User::create(['name'=>'Province fixture','username'=>'blade-pr','password'=>'Fixture-Password42!','person_type'=>'staff','organization_id'=>Organization::where('type','governorate')->value('id'),'role_id'=>Role::where('name','province_public_relations')->value('id'),'access_scope'=>'organization']);
        $this->actingAs($user,'web');
        $this->get('/panel/admin/annual-slogan')->assertOk();
        $response=$this->post('/web-api/v1/admin/annual-slogan',['file'=>UploadedFile::fake()->image('year.png',300,140)],['Accept'=>'application/json'])->assertOk();
        $url=$response->json('data.url');
        $this->assertSame($url,Setting::where('key','site.slogan')->value('value')['url']);
        $this->get('/')->assertOk()->assertSee($url,false);
        $this->get('/amol')->assertOk()->assertSee($url,false);
        $this->postJson('/web-api/v1/admin/contents',[])->assertForbidden();
    }

    public function test_optional_relations_and_album_publish_through_the_native_web_api(): void
    {
        $admin=User::where('username','admin')->firstOrFail();$this->actingAs($admin,'web');
        $created=$this->postJson('/web-api/v1/admin/contents',['type'=>'news','title'=>'Blade article fixture','body'=>'<p>Full article content</p><div class="portal-image-album portal-image-album-columns-2"><div class="portal-image-album-item"><img src="/fixture/one.webp" alt="one"></div><div class="portal-image-album-item"><img src="/fixture/two.webp" alt="two"></div></div>','status'=>'published','published_at'=>now()->subMinute()->toDateTimeString(),'tag_ids'=>[],'media_ids'=>[]])->assertCreated();
        $id=$created->json('data.id');
        $this->get('/content/'.$id)->assertOk()->assertSee('Full article content')->assertSee('portal-image-album-columns-2',false)->assertSee('/fixture/two.webp',false);
        $this->get('/panel/admin/news/'.$id.'/edit')->assertOk();
        $this->get('/amol/content/'.$id)->assertNotFound();
    }

    public function test_registration_contains_every_clause_and_demo_does_not_grant_management_access(): void
    {
        $response=$this->get('/register')->assertOk();
        $terms=json_decode(file_get_contents(resource_path('legal/citizen-registration-terms-1.0.json')),true);
        $plain=html_entity_decode(strip_tags($response->getContent()),ENT_QUOTES|ENT_HTML5,'UTF-8');
        foreach($terms['articles'] as $article){$response->assertSee('data-terms-article="'.$article['number'].'"',false);foreach($article['clauses'] as $clause)$this->assertStringContainsString(str_replace('**','',$clause),$plain);}
        config(['website.citizen_demo'=>true]);
        $this->get('/citizen')->assertOk();
        $this->get('/panel/admin')->assertRedirect();
    }

    public function test_every_published_official_section_and_legacy_county_link_resolves(): void
    {
        $this->seed(OfficialPublicSiteSeeder::class);
        foreach(app(PortalView::class)->data('frontend')['officialSections'] as $section){
            $href=$section['href'];
            if(str_starts_with($href,'/') && !str_starts_with($href,'//')) { $response=$this->followingRedirects()->get($href); $this->assertSame(200,$response->status(),$href); $response->assertDontSee("@include(",false); }
        }
        $this->get('/office?name=amol&section=about')->assertRedirect('/amol/about');
        $this->get('/news?name=amol&q=example')->assertRedirect('/amol/news?q=example');
        $ui=app(PortalView::class);$county=$ui->county('amol');
        $this->assertSame('/design/shared/seal.png',$ui->href('/design/shared/seal.png',$county));
        $this->assertSame('/sari',$ui->href('/office?name=sari',$county));
        $this->get('/rss')->assertOk()->assertSee('<?xml version="1.0" encoding="UTF-8"?>',false);
    }

    public function test_published_dynamic_form_renders_schema_and_accepts_empty_optional_answers(): void
    {
        $form=DynamicForm::create(['title'=>'Native form fixture','slug'=>'native-fixture','is_active'=>true,'locale'=>'fa','fields'=>[
            ['key'=>'optional','label'=>'Optional select','type'=>'select','options'=>['الف','ب']],
            ['key'=>'choices','label'=>'Multiple choices','type'=>'multi_select','options'=>['الف','ب']],
            ['key'=>'count','label'=>'Count','type'=>'number','min'=>0,'required'=>true],
            ['key'=>'confirmed','label'=>'Confirmation','type'=>'boolean'],
            ['key'=>'file','label'=>'Optional file','type'=>'file'],
        ]]);
        $citizen=User::create(['name'=>'Citizen form fixture','person_type'=>'citizen','is_active'=>true,'role_id'=>Role::where('name','citizen')->value('id')]);
        $this->actingAs($citizen,'web');
        $this->get('/forms/'.$form->id)->assertOk()->assertSee('data-answer-key="choices"',false)->assertSee('data-answer-type="file"',false);
        $result=$this->postJson('/web-api/v1/citizen/forms/'.$form->id.'/submissions',['answers'=>['optional'=>null,'choices'=>['الف','ب'],'count'=>0,'confirmed'=>false,'file'=>null]])->assertCreated();
        $this->assertSame(0,$result->json('data.data.count'));
        $this->getJson('/web-api/v1/citizen/submissions/'.$result->json('data.reference'))->assertOk();
    }

    public function test_real_citizen_registration_verifies_into_an_http_only_web_session(): void
    {
        config(['portal.otp.driver'=>'log','portal.otp.expose_local'=>true]);
        $challenge=$this->postJson('/web-api/v1/auth/register',['name'=>'شهروند آزمایش انتقال','mobile'=>'09120005511','national_code'=>'0084575948','terms_accepted'=>true,'terms_version'=>RegistrationTerms::VERSION,'terms_document_sha256'=>RegistrationTerms::documentHash()])->assertOk()->json('data');
        $this->postJson('/session/verify',['challenge_id'=>$challenge['challenge_id'],'code'=>$challenge['dev_code']])->assertOk()->assertJsonMissingPath('data.token');
        $this->assertAuthenticated('web');
        $this->assertDatabaseCount('personal_access_tokens',0);
        $this->get('/citizen')->assertOk();
        $this->getJson('/web-api/v1/citizen/dashboard')->assertOk();
        $this->get('/panel/admin')->assertForbidden();
    }
}
