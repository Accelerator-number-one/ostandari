<?php

namespace Tests\Feature;

use App\Models\{Content, Organization, User};
use App\Services\PortalView;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class PublicPortalLayoutTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function article(string $title, array $attributes = []): Content
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        return Content::create($attributes + ['type' => 'news', 'title' => $title, 'slug' => str()->slug($title),
            'body' => '<p>متن مطلب</p>', 'locale' => 'fa', 'organization_id' => $admin->organization_id,
            'author_id' => $admin->id, 'status' => 'published', 'published_at' => now()->subMinute()]);
    }

    public function test_news_tabs_and_archives_select_visible_content_in_the_correct_scope(): void
    {
        $county = Organization::where('slug', 'county-amol')->firstOrFail();
        $province = $this->article('Province report');
        $governorate = $this->article('Governorate report', ['organization_id' => $county->id]);
        $counties = $this->article('County affairs', ['metadata' => ['news_scope' => 'counties']]);
        $crisis = $this->article('Crisis report', ['metadata' => ['news_scope' => 'crisis']]);
        $this->article('Draft crisis', ['metadata' => ['news_scope' => 'crisis'], 'status' => 'draft']);
        $this->article('Expired crisis', ['metadata' => ['news_scope' => 'crisis'], 'expires_at' => now()->subSecond()]);
        $feeds = $this->getJson('/api/v1/home')->assertOk()->json('data.news_feeds');
        foreach (['province' => $province, 'governorates' => $governorate, 'counties' => $counties, 'crisis' => $crisis] as $scope => $content) {
            $this->assertSame([$content->id], array_column($feeds[$scope], 'id'), $scope);
        }
        $this->get('/news?news_scope=governorates')->assertOk()->assertSee('Governorate report')->assertDontSee('Province report');
        $this->get('/amol/news?news_scope=crisis')->assertOk()->assertDontSee('Crisis report');
        $this->getJson('/news?news_scope=invalid')->assertUnprocessable();
        $this->get('/')->assertOk()->assertSee('اخبار مدیریت بحران')->assertDontSee('آخرین مصوبات و اطلاعیه‌های سازمان را مطالعه کنید.');
    }

    public function test_news_group_is_editable_without_new_columns_and_preserves_existing_metadata(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin, 'web');
        $response = $this->postJson('/web-api/v1/admin/contents', ['type' => 'news', 'title' => 'خبر مدیریت بحران',
            'body' => '<p>متن کامل خبر</p>', 'status' => 'published', 'news_scope' => 'crisis', 'metadata' => ['existing' => 'preserved']])->assertCreated();
        $id = $response->json('data.id');
        $this->assertSame('crisis', $response->json('data.news_scope'));
        $this->patchJson('/web-api/v1/admin/contents/'.$id, ['news_scope' => 'counties'])->assertOk();
        $content = Content::findOrFail($id);
        $this->assertSame('preserved', $content->metadata['existing']);
        $this->assertSame('counties', $content->news_scope);
        $this->get('/panel/admin/news/new')->assertOk()->assertSee('data-field="news_scope"', false);
    }

    public function test_each_duty_has_an_individual_page_and_news_cannot_be_requested_as_a_duty(): void
    {
        $duty = $this->article('Planning duties', ['type' => 'duty']);
        $news = $this->article('Ordinary news');
        $this->get('/duties')->assertOk()->assertSee('/duties/'.$duty->id, false);
        $this->get('/duties/'.$duty->id)->assertOk()->assertSee('Planning duties')->assertSee('متن مطلب');
        $this->get('/duties/'.$news->id)->assertNotFound();
    }

    public function test_useful_links_directory_uses_the_same_fallback_as_the_home_page(): void
    {
        $links = app(PortalView::class)->usefulLinks();
        $this->assertNotEmpty($links);
        $this->get('/')->assertOk()->assertSee($links[0]['title'])->assertDontSee('پیوندهای مفید سازمان را اینجا مطالعه کنید.');
        $this->get('/links')->assertOk()->assertSee($links[0]['title']);
    }

    public function test_county_pages_have_shared_banners_and_a_carousel_without_the_old_hero_or_breadcrumb(): void
    {
        foreach (app(PortalView::class)->data('frontend')['counties'] as $county) {
            $this->get('/'.$county['slug'])->assertOk()->assertSee('فرمانداری شهرستان '.$county['name'])
                ->assertSee('portal-carousel-arrow', false)->assertDontSee('پرتال اطلاع‌رسانی شهرستان')
                ->assertDontSee('source-breadcrumb', false)->assertSee('/design/county-banners/annual-slogan.png', false);
        }
        $this->get('/amol')->assertSee('/design/county-banners/amol.png', false);
        $this->get('/nur')->assertSee('portal-county-banner-title', false)->assertSee('فرمانداری شهرستان نور');
    }
}
