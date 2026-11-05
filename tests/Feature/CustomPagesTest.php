<?php

namespace Tests\Feature;

use App\Models\{Content, CustomPage, Media, Menu, MenuItem, Organization, Role, User};
use App\Services\{CustomPages, PortalView};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

final class CustomPagesTest extends TestCase
{
    use DatabaseMigrations;
    private User $admin;
    private User $province;
    private User $amol;
    private User $nur;

    protected function setUp(): void
    {
        parent::setUp(); $this->seed(DatabaseSeeder::class); Storage::fake('public');
        $this->admin=User::where('username','admin')->firstOrFail();
        foreach(['province','amol','nur'] as $name){
            $org=$name==='province'?Organization::where('type','governorate')->firstOrFail():Organization::where('slug','county-'.$name)->firstOrFail();
            $this->$name=User::create(['name'=>$name.' page editor','username'=>'pages_'.$name,'password'=>'Fixture-Only-Password42!',
                'role_id'=>Role::where('name',$name==='province'?'province_public_relations':'county_public_relations')->value('id'),
                'organization_id'=>$org->id,'person_type'=>'staff','access_scope'=>'organization']);
        }
    }
    private function as(User $user): void { $this->app['auth']->forgetGuards(); $this->actingAs($user,'web'); }
    private function payload(array $values=[]): array
    {
        return $values+['title'=>'A custom page','path'=>'/custom-example','body'=>'<h2>Page heading</h2><p>Full custom-page content.</p>',
            'link_title'=>'A custom link','link_style'=>'text','link_image_media_id'=>null,'placements'=>[],'status'=>'published','priority'=>0];
    }
    private function image(User $owner): Media
    {
        return Media::create(['user_id'=>$owner->id,'organization_id'=>$owner->organization_id,'disk'=>'public',
            'path'=>'fixtures/'.$owner->id.'.png','original_name'=>'Own image.png','mime'=>'image/png','visibility'=>'public','size'=>30]);
    }

    public function test_general_information_disappears_from_navigation_links_sitemaps_and_old_urls_without_deleting_data(): void
    {
        $menu=Menu::create(['organization_id'=>$this->admin->organization_id,'name'=>'Main','key'=>'official-main']);
        MenuItem::create(['menu_id'=>$menu->id,'title'=>'اطلاعات عمومی','url'=>'/section/menu-5']);
        MenuItem::create(['menu_id'=>$menu->id,'title'=>'Keep this link','url'=>'/contact']);
        $old=Content::create(['organization_id'=>$this->admin->organization_id,'author_id'=>$this->admin->id,'type'=>'page',
            'title'=>'اطلاعات عمومی','slug'=>'official-page-menu-5','body'=>'Original data stays','status'=>'published','published_at'=>now()->subDay()]);
        foreach(['/','/links','/about','/sitemap','/amol','/amol/links'] as $path)
            $this->get($path)->assertOk()->assertDontSee('اطلاعات عمومی')->assertDontSee('/section/menu-5"',false)->assertDontSee('/about/general"',false);
        foreach(['/section/menu-5','/about/general','/amol/section/menu-5','/amol/about/general'] as $path)$this->get($path)->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/section/menu-5</loc>',false)->assertDontSee('/about/general</loc>',false);
        $this->getJson('/api/v1/menus')->assertOk()->assertDontSee('اطلاعات عمومی')->assertSee('Keep this link');
        $this->assertSame('Original data stays',$old->fresh()->body);
        $this->get('/content/'.$old->id)->assertNotFound();
    }

    public function test_provincial_public_relations_can_build_pages_without_changing_other_read_only_permissions(): void
    {
        $this->as($this->province);
        $this->get('/panel/admin/custom-pages')->assertOk()->assertSee('ساخت صفحه جدید')->assertSee('data-custom-page-builder',false)
            ->assertSee('data-can-write="true"',false)->assertSee('data-rich-text="true"',false);
        $id=$this->postJson('/web-api/v1/admin/custom-pages',$this->payload())->assertCreated()->assertJsonPath('data.organization_id',$this->province->organization_id)->json('data.id');
        $this->get('/custom-example')->assertOk()->assertSee('Page heading')->assertSee('Full custom-page content.')
            ->assertSee('data-alpine-header',false)->assertSee('data-source-footer',false);
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$id,['body'=>'<p>Updated page body</p><script>alert(1)</script>'])->assertOk();
        $this->get('/custom-example')->assertOk()->assertSee('Updated page body')->assertDontSee('<script>alert(1)</script>',false);
        $this->postJson('/web-api/v1/admin/contents',['type'=>'news','title'=>'Not permitted','body'=>'Text'])->assertForbidden();
        // Provincial PR may upload files for its editable directories; a missing file is still rejected.
        $this->postJson('/web-api/v1/media',['visibility'=>'public'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['path'=>'/forged-owner','organization_id'=>$this->amol->organization_id]))->assertForbidden();
        $this->assertSame($this->province->id,CustomPage::findOrFail($id)->author_id);
    }

    public function test_each_county_owns_its_routes_pages_images_and_management_entry_points(): void
    {
        $ids=[];
        foreach([$this->amol,$this->nur,$this->province] as $actor){
            $this->as($actor);
            $ids[$actor->id]=$this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['title'=>$actor->name,'placements'=>['links']]))->assertCreated()->json('data.id');
        }
        $this->get('/amol/custom-example')->assertOk()->assertSee('amol page editor')->assertDontSee('nur page editor')->assertDontSee('province page editor');
        $this->get('/nur/custom-example')->assertOk()->assertSee('nur page editor')->assertDontSee('amol page editor');
        $this->get('/custom-example')->assertOk()->assertSee('province page editor')->assertDontSee('amol page editor');
        $this->as($this->amol);
        $this->get('/county-admin/amol/custom-pages')->assertOk()->assertSee('data-public-prefix="/amol"',false)->assertSee('data-can-write="true"',false);
        $this->get('/county-admin/nur/custom-pages')->assertForbidden();
        foreach([$ids[$this->nur->id],$ids[$this->province->id]] as $foreign){
            $this->getJson('/web-api/v1/admin/custom-pages/'.$foreign)->assertNotFound();
            $this->patchJson('/web-api/v1/admin/custom-pages/'.$foreign,['title'=>'Intrusion'])->assertNotFound();
            $this->deleteJson('/web-api/v1/admin/custom-pages/'.$foreign)->assertNotFound();
            $this->postJson('/web-api/v1/admin/custom-pages/'.$foreign.'/restore')->assertNotFound();
        }
        $rows=$this->getJson('/web-api/v1/admin/custom-pages')->assertOk()->json('data');
        $this->assertSame([$ids[$this->amol->id]],array_column($rows,'id'));
        $foreign=$this->image($this->nur);
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['path'=>'/bad-image','link_style'=>'image','link_image_media_id'=>$foreign->id]))->assertForbidden();
        $this->getJson('/web-api/v1/admin/custom-pages/images')->assertOk()->assertJsonCount(0,'data');
    }

    public function test_routes_cannot_replace_existing_features_escape_a_county_or_collide_with_deleted_pages(): void
    {
        $this->as($this->amol);
        foreach(['/','/news','/news/old','/panel/admin','/api/v1/home','/web-api','/storage/x','/page-assets/x','/section/menu-5',
            '/about/general','/nur/test','/sari/test','/noor/test','/ghalugah/test','/آمل/test','//example.test','https://example.test','/a/../b','/a%2fb','/bad?x=1','/bad#x','/bad\\x'] as $path)
            $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['path'=>$path]))->assertUnprocessable();
        $id=$this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['path'=>'/Unique-Route/']))->assertCreated()->assertJsonPath('data.path','/unique-route')->json('data.id');
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['path'=>'unique-route']))->assertUnprocessable();
        $this->deleteJson('/web-api/v1/admin/custom-pages/'.$id)->assertNoContent();
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['path'=>'unique-route']))->assertUnprocessable();
        $this->postJson('/web-api/v1/admin/custom-pages/'.$id.'/restore')->assertOk();
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['path'=>'/گردشگری']))->assertCreated();
        $this->get('/amol/'.rawurlencode('گردشگری'))->assertOk()->assertSee('Full custom-page content.');
    }

    public function test_link_placements_follow_publication_path_edits_archive_delete_and_restore(): void
    {
        $this->as($this->admin);$image=$this->image($this->admin);
        $id=$this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['status'=>'draft','link_style'=>'image','link_image_media_id'=>$image->id,
            'placements'=>array_keys(CustomPages::PLACEMENTS)]))->assertCreated()->json('data.id');
        $this->get('/custom-example')->assertNotFound();
        $this->get('/')->assertDontSee('A custom link');
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$id,['status'=>'published'])->assertOk();
        $this->get('/')->assertOk()->assertSee('A custom link')->assertSee('portal-custom-link-image',false)->assertSee('portal-custom-banners',false);
        foreach(['/links','/quick-access','/sitemap','/sitemap.xml'] as $path)$this->get($path)->assertOk()->assertSee('/custom-example',false);
        $this->get('/amol')->assertDontSee('A custom link');
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$id,['path'=>'/new-page-address','title'=>'Updated custom title'])->assertOk();
        $this->get('/custom-example')->assertNotFound();$this->get('/new-page-address')->assertOk()->assertSee('Updated custom title');
        $this->get('/')->assertSee('/new-page-address',false)->assertDontSee('/custom-example',false);
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$id,['status'=>'archived'])->assertOk();
        $this->get('/new-page-address')->assertNotFound();$this->get('/')->assertDontSee('A custom link');
        $this->patchJson('/web-api/v1/admin/custom-pages/'.$id,['status'=>'published'])->assertOk();
        $this->deleteJson('/web-api/v1/admin/custom-pages/'.$id)->assertNoContent();
        $this->get('/new-page-address')->assertNotFound();$this->get('/')->assertDontSee('A custom link');
        $this->postJson('/web-api/v1/admin/custom-pages/'.$id.'/restore')->assertOk();
        $this->get('/new-page-address')->assertOk();
    }

    public function test_page_images_survive_loss_of_local_storage_and_used_files_cannot_be_deleted(): void
    {
        $this->as($this->province);
        $upload=$this->post('/web-api/v1/admin/custom-pages/image',['file'=>UploadedFile::fake()->image('banner.png',600,180)],['Accept'=>'application/json'])
            ->assertCreated()->json('data');
        $this->assertSame($this->province->organization_id,$upload['organization_id']);
        $this->assertStringStartsWith('/page-assets/',$upload['url']);
        $this->assertNotEmpty(DB::table('custom_page_images')->where('media_id',$upload['id'])->value('base64'));
        Storage::fake('public');
        $this->get($upload['url'])->assertOk()->assertHeader('Content-Type',$upload['mime']);
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['link_style'=>'image','link_image_media_id'=>$upload['id'],
            'placements'=>['links','banner'],'body'=>'<p>Photo page</p><img src="'.$upload['url'].'" alt="Own image">']))->assertCreated();
        $this->get('/custom-example')->assertOk()->assertSee($upload['url'],false);
        $this->as($this->admin);$this->deleteJson('/web-api/v1/media/'.$upload['id'])->assertConflict();
        $this->get('/web-api/v1/media/'.$upload['id'].'/download')->assertOk();
        $this->as($this->amol);
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['body'=>'<img src="'.$upload['url'].'">']))->assertForbidden();
        $this->getJson('/web-api/v1/admin/custom-pages/images')->assertOk()->assertJsonCount(0,'data');
        $this->get('/page-assets/'.$upload['id'].'/wrong.webp')->assertNotFound();
    }

    public function test_validation_authentication_and_setup_do_not_modify_existing_accounts_or_content(): void
    {
        $this->get('/panel/admin/custom-pages')->assertRedirect();
        $accounts=User::pluck('password','id')->all();$roles=Role::pluck('permissions','id')->all();
        $this->as($this->admin);
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['body'=>'<script>alert(1)</script>']))->assertUnprocessable();
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['link_style'=>'image']))->assertUnprocessable();
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['placements'=>['banner']]))->assertUnprocessable();
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload(['placements'=>['invalid']]))->assertUnprocessable();
        $this->artisan('portal:prepare-pages')->assertExitCode(0);
        $this->assertSame($accounts,User::pluck('password','id')->all());$this->assertSame($roles,Role::pluck('permissions','id')->all());
        $operator=User::create(['name'=>'Operator','username'=>'pages_operator','password'=>'Fixture-Only-Password42!',
            'person_type'=>'staff','organization_id'=>$this->admin->organization_id,'role_id'=>Role::where('name','operator')->value('id')]);
        $this->as($operator);$this->get('/panel/admin/custom-pages')->assertForbidden();
        $this->postJson('/web-api/v1/admin/custom-pages',$this->payload())->assertForbidden();
        $this->postJson('/web-api/v1/admin/custom-pages/image',[])->assertForbidden();
    }
}
