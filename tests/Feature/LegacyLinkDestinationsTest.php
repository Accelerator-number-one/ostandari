<?php

namespace Tests\Feature;

use App\Services\{Html, LegacyLinkPages, PortalView, ResourceRegistry};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;

final class LegacyLinkDestinationsTest extends TestCase
{
    public function test_only_the_exact_old_domain_and_www_alias_are_matched(): void
    {
        foreach (['https://ostan-mz.ir/x', 'http://www.ostan-mz.ir/x', '//ostan-mz.ir/x'] as $url)
            $this->assertStringStartsWith('/imported-pages/', LegacyLinkPages::destination($url));
        foreach (['https://ostan-mz.ir.example.com/x', 'https://notostan-mz.ir/x',
            'https://images.ostan-mz.ir/x', 'https://ostan-mz.s3.ir-thr-at1.arvanstorage.ir/file.pdf',
            'https://example.com/?next=https://ostan-mz.ir/x', 'https://ostan-mz.ir@example.com/x',
            'https://user:pass@ostan-mz.ir/x', '/existing-page', 'mailto:info@ostan-mz.ir'] as $url)
            $this->assertNull(LegacyLinkPages::destination($url));
    }

    public function test_scheme_www_query_order_and_fragments_do_not_create_duplicate_pages(): void
    {
        $first = 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=49&OrgId=1';
        $alias = 'http://www.ostan-mz.ir/home/showdetailsmenucontent/?OrgId=1&amp;MenuId=49';
        $this->assertSame(LegacyLinkPages::source($first), LegacyLinkPages::source($alias));
        $this->assertSame(LegacyLinkPages::key($first), LegacyLinkPages::key($alias.'#goal'));
        $this->assertSame(LegacyLinkPages::destination($first).'#goal', LegacyLinkPages::destination($alias.'#goal'));
        $this->assertNotSame(LegacyLinkPages::destination($first), LegacyLinkPages::destination(str_replace('49', '50', $first)));
    }

    public function test_existing_directories_stay_working_and_placeholder_paths_are_global(): void
    {
        $ui = app(PortalView::class);
        $this->assertSame('/managers?group=3', $ui->href('https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=62&OrgId=1'));
        $this->assertSame('/links', $ui->href('https://ostan-mz.ir/Home/LinkServiceList?OrgId=1'));
        $this->assertSame('/', $ui->href('https://ostan-mz.ir/'));
        $url = 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=49&OrgId=1';
        $this->assertSame(LegacyLinkPages::destination($url), $ui->href($url, ['slug' => 'amol']));
    }

    public function test_rich_text_changes_only_anchor_destinations_and_keeps_images_and_other_hosts(): void
    {
        $source = 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=49&amp;OrgId=1';
        $html = '<p><a href="'.$source.'">شرح وظایف</a><a href="https://moi.ir/">وزارت کشور</a><img src="https://ostan-mz.ir/photo.jpg" alt="عکس"></p>';
        $rendered = Html::articleBody($html);
        $this->assertStringContainsString('href="'.LegacyLinkPages::destination($source).'"', $rendered);
        $this->assertStringContainsString('href="https://moi.ir/"', $rendered);
        $this->assertStringContainsString('src="https://ostan-mz.ir/photo.jpg"', $rendered);
        $this->assertStringContainsString('شرح وظایف', $rendered);
    }

    public function test_a_blank_page_renders_its_title_without_invented_source_content(): void
    {
        $html = view('site.custom-page', ['customPage' => ['id' => 1, 'title' => 'خدمات الکترونیکی استان', 'body' => '']])->render();
        $this->assertStringContainsString('خدمات الکترونیکی استان', $html);
        $this->assertStringNotContainsString('محتوا در حال آماده‌سازی', $html);
        $this->assertStringNotContainsString('ostan-mz.ir', $html);
    }

    public function test_all_old_domain_links_in_the_existing_public_inventory_are_internal(): void
    {
        $ui = app(PortalView::class);
        $count = 0;
        foreach ($ui->data('official-site')['links'] as $link) {
            if (!LegacyLinkPages::source($link['url'])) continue;
            $path = $ui->href($link['url']);
            $this->assertStringStartsWith('/', $path);
            $this->assertStringNotContainsString('ostan-mz.ir', $path);
            $count++;
        }
        $this->assertGreaterThan(0, $count);
    }

    public function test_local_service_and_slide_links_can_be_saved_without_accepting_unsafe_schemes(): void
    {
        foreach (['services', 'slides'] as $resource) {
            $rule = ResourceRegistry::get($resource)['rules']['url'];
            $this->assertTrue(Validator::make(['url' => '/imported-pages/example'], ['url' => $rule])->passes());
            $this->assertTrue(Validator::make(['url' => 'https://moi.ir/'], ['url' => $rule])->passes());
            foreach (['javascript:alert(1)', '//example.com', '/\\example.com'] as $url)
                $this->assertFalse(Validator::make(['url' => $url], ['url' => $rule])->passes());
        }
    }
}
