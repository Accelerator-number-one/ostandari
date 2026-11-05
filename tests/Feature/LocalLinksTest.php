<?php

namespace Tests\Feature;

use App\Services\{LegacyLinkPages, LocalLinks, PortalView};
use Illuminate\Foundation\Testing\TestCase;

final class LocalLinksTest extends TestCase
{
    public function test_old_link_indexes_open_the_local_directory(): void
    {
        foreach (['https://ostan-mz.ir/Home/LinkServiceList?OrgId=1',
            'http://www.ostan-mz.ir/Home/LinkServiceList?OrgId=1',
            'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=9&OrgId=1'] as $source) {
            $this->assertSame('/links', app(PortalView::class)->href($source));
        }
    }

    public function test_other_sites_are_preserved_and_legacy_content_opens_an_editable_local_page(): void
    {
        foreach (['https://example.com/Home/LinkServiceList?OrgId=1',
            'https://ostan-mz.ir/Home/LinkServiceList?OrgId=14',
            'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=49&OrgId=1',
            'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=50&OrgId=1',
            'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=1&OrgId=1',
            'https://ostan-mz.ir/Home/ElectronicServiceList?OrgId=1',
            'http://www.leader.ir/fa', 'http://www.president.ir/fa',
            'https://moi.ir/Portal/Home/default.aspx', 'http://www.parliran.ir/'] as $source) {
            $this->assertNull(LocalLinks::destination($source));
            $this->assertSame(LegacyLinkPages::destination($source) ?? $source, app(PortalView::class)->href($source));
        }
        $this->assertNull(LocalLinks::destination('https://ostan-mz.ir/Home/LinkServiceList?OrgId[]=1'));
    }

    public function test_all_positions_are_grouped_without_the_old_fourteen_or_hundred_record_cutoff(): void
    {
        $items = [];
        foreach (range(1, 121) as $id) $items[] = ['id' => $id, 'title' => 'پیوند '.$id, 'url' => 'https://example.com/'.$id,
            'category_id' => $id % 2, 'category' => ['name' => $id % 2 ? 'خدمات' : 'سایر پیوندها'], 'position' => $id % 2 ? 'footer' : 'useful'];
        $items[] = ['id' => 122, 'title' => 'اطلاعات عمومی', 'url' => '/section/menu-5'];
        $items[] = ['id' => 123, 'title' => 'صفحه جدید', 'href' => '/new-page', 'custom_page' => true];
        $groups = LocalLinks::groups($items);
        $this->assertCount(3, $groups);
        $this->assertSame(122, array_sum(array_map(fn ($group) => count($group['items']), $groups)));
        $this->assertSame('صفحه‌های سایت', $groups[2]['title']);
        $this->assertSame('https://example.com/1', $groups[0]['items'][0]['url']);
    }

    public function test_directory_markup_keeps_the_actual_destinations_and_escapes_titles(): void
    {
        $items = [['title' => 'وزارت کشور', 'url' => 'https://moi.ir/Portal/Home/default.aspx'],
            ['title' => 'فهرست پیوندها', 'url' => 'https://ostan-mz.ir/Home/LinkServiceList?OrgId=1'],
            ['title' => '<script>alert(1)</script>', 'url' => 'https://example.com/']];
        $html = view('site.links-directory', ['linkGroups' => LocalLinks::groups($items), 'county' => null])->render();
        $this->assertStringContainsString('href="https://moi.ir/Portal/Home/default.aspx"', $html);
        $this->assertStringContainsString('href="/links"', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('@include(', $html);
        $this->assertStringContainsString('جستجوی پیوند', $html);
    }
}
