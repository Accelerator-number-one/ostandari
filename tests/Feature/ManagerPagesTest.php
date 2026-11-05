<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\{MediaController, ResourceController};
use App\Models\{Content, Organization, Role, User};
use App\Services\{LegacyLinkPages, ManagerPages, PortalView, ResourceRegistry};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

/** Tests URL resolution, existing form validation and bounded permissions without a database. */
final class ManagerPagesTest extends TestCase
{
    private function actor(string $organizationType = 'governorate', bool $active = true): User
    {
        $user = (new User)->forceFill(['person_type' => 'staff', 'is_active' => $active, 'organization_id' => 1]);
        $user->setRelation('role', (new Role)->forceFill(['name' => 'province_public_relations',
            'permissions' => ['members.manage', 'content.manage', 'media.manage']]));
        $user->setRelation('organization', (new Organization)->forceFill(['type' => $organizationType, 'is_active' => true]));
        return $user;
    }

    private function request(User $user, string $resource, string $controller = ResourceController::class): Request
    {
        $request = Request::create('/web-api/v1/admin/'.$resource, 'POST');
        $route = new Route('POST', 'web-api/v1/admin/{resource}', $controller.'@store');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_all_five_legacy_links_resolve_to_the_matching_existing_database_directory(): void
    {
        $ui = app(PortalView::class);
        foreach (ManagerPages::GROUPS as $group => $definition) {
            $path = '/managers?group='.$group;
            $source = 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId='.$definition['menu'].'&OrgId=1';
            $this->assertSame($path, $ui->href($source));
            $this->assertSame($path, $ui->href(str_replace('ostan-mz.ir', 'www.ostan-mz.ir', $source)));
            $this->assertSame($path, $ui->href('/section/menu-'.$definition['menu']));
            $this->assertSame($path, $ui->href($path));
        }
    }

    public function test_other_hosts_and_customized_destinations_are_preserved_while_old_county_urls_are_internal(): void
    {
        foreach (['https://example.com/Home/ShowDetailsMenuContent?MenuId=62&OrgId=1',
            'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=62&OrgId=14',
            'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=999&OrgId=1',
            'https://www.leader.ir/', '/my-new-directory'] as $path) {
            $this->assertNull(ManagerPages::internalHref($path));
            $this->assertSame(LegacyLinkPages::destination($path) ?? $path, app(PortalView::class)->href($path));
        }
        $this->assertNull(ManagerPages::internalHref('/Home/ShowDetailsMenuContent?MenuId[]=62'));
        $this->assertNull(ManagerPages::internalHref('/Home/ShowDetailsMenuContent?MenuId=62&OrgId[]=1'));
    }

    public function test_link_editors_accept_internal_destinations_and_reject_unsafe_urls(): void
    {
        $rule = ResourceRegistry::get('links')['rules']['url'];
        foreach (['/managers?group=3', '/managers?group=5', ManagerPages::PDF_URL] as $url) {
            $this->assertTrue(Validator::make(['url' => $url], ['url' => $rule])->passes());
        }
        foreach (['javascript:alert(1)', '//example.com', '/\\example.com', 'https://example.com/bad path'] as $url) {
            $this->assertFalse(Validator::make(['url' => $url], ['url' => $rule])->passes());
        }
    }

    public function test_provincial_pr_can_manage_directories_but_other_resources_remain_read_only(): void
    {
        $user = $this->actor();
        $this->assertTrue($user->hasReadOnlyWorkspace());
        foreach (['members', 'links'] as $resource) $this->assertTrue(ManagerPages::mayWrite($this->request($user, $resource)));
        $governor = $this->request($user, 'members');
        $governor->merge(['kind' => 'governor']);
        $this->assertFalse(ManagerPages::mayWrite($governor));
        foreach (['news', 'settings', 'organizations', 'users', 'categories', 'contents'] as $resource) {
            $this->assertFalse(ManagerPages::mayWrite($this->request($user, $resource)));
        }
        $this->assertTrue(ManagerPages::mayWrite($this->request($user, 'media', MediaController::class)));
        $this->assertFalse(ManagerPages::mayWrite($this->request($this->actor('county'), 'members')));
        $this->assertFalse(ManagerPages::mayWrite($this->request($this->actor('governorate', false), 'members')));
    }

    public function test_the_editable_pdf_block_renders_only_on_the_directory_not_the_homepage(): void
    {
        $page = (new Content)->forceFill(['body' => '<p><a href="'.e(ManagerPages::PDF_URL).'">'.ManagerPages::PDF_TITLE.'</a></p>']);
        $data = ['items' => [], 'view' => 'members', 'county' => null, 'directoryPage' => $page, 'home' => []];
        $html = view('site.members', $data)->render();
        $this->assertStringContainsString(ManagerPages::PDF_TITLE, $html);
        $this->assertStringContainsString(ManagerPages::PDF_URL, $html);
        $this->assertStringNotContainsString(ManagerPages::PDF_TITLE, view('site.members', [...$data, 'view' => 'home'])->render());
        $page->body = '<p><a href="https://example.com/new-list.pdf">فهرست جدید</a></p>';
        $this->assertStringContainsString('https://example.com/new-list.pdf', view('site.members', $data)->render());
    }
}
