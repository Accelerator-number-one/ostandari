<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\PublicController;
use App\Models\{StaffPortal, StaffPortalItem, StaffPortalPage};
use App\Services\{PortalView, StaffPages, StaffPortals};
use Illuminate\Http\Request;

final class StaffPublicController
{
    public function __invoke(Request $request, PortalView $ui, PublicController $public, string $slug, ?int $id = null)
    {
        $portal = StaffPortals::resolve($slug);
        $item = $id ? $portal->items()->published()->with('asset')->whereIn('kind', ['news', 'announcement'])->findOrFail($id) : null;
        return $this->render($request, $ui, $public, $portal, $item);
    }

    public function page(Request $request, PortalView $ui, PublicController $public, string $slug, string $path)
    {
        $portal = StaffPortals::resolve($slug);
        abort_unless(StaffPages::ready(), 404);
        $page = $portal->pages()->published()->where('path', $path)->firstOrFail();
        return $this->render($request, $ui, $public, $portal, null, $page);
    }

    private function render(Request $request, PortalView $ui, PublicController $public, StaffPortal $portal, ?StaffPortalItem $item = null, ?StaffPortalPage $customPage = null)
    {
        $tab = $request->query('tab', 'news');
        abort_unless(in_array($tab, ['news', 'announcement', 'gallery', 'video', 'about'], true), 404);
        // Old gallery URLs still work; the upper navigation has exactly three tabs.
        if (in_array($tab, ['gallery', 'video'], true)) $tab = 'news';
        $home = $public->home($request->duplicate(['locale' => 'fa']))->getData(true)['data'];
        $template = collect($ui->data('frontend')['templates'])->firstWhere('path', '/');
        $page = [...$template, 'id' => 'StaffPublicPage', 'path' => $request->getPathInfo(), 'resource' => '', 'serverRendered' => true, 'controller' => 'site'];
        $slides = $portal->items()->published()->where('kind', 'slide')->whereNotNull('asset_id')->with('asset')->orderBy('priority')->orderBy('id')->get()
            ->map(fn ($slide) => ['title' => $slide->title, 'image' => $slide->asset?->url, 'url' => $slide->url, 'caption' => true])->all();
        if (!$slides && $portal->banner) $slides = [['title' => '', 'image' => $portal->banner->url, 'url' => null, 'caption' => false]];
        if (!$slides) $slides = collect($home['slides'] ?? [])->map(fn ($slide) => ['title' => '', 'caption' => false,
            'image' => $ui->image($slide), 'url' => !empty($slide['content_id']) ? '/content/'.$slide['content_id'] : ($slide['url'] ?? null)])->filter(fn ($slide) => $slide['image'])->values()->all();
        if (!$slides) $slides = [['title' => '', 'image' => '/design/site/assets/image/baner.png', 'url' => null, 'caption' => false]];
        // Both feeds are already rendered, so switching tabs never requests another page.
        $feeds = [];
        if (!$item && !$customPage) foreach (['news', 'announcement'] as $kind) {
            $pageName = $kind.'_page';
            $legacyPage = $tab === $kind && !$request->has($pageName) && $request->has('page') ? max(1, $request->integer('page')) : null;
            $feeds[$kind] = $portal->items()->published()->where('kind', $kind)->with('asset')->orderBy('priority')->latest('published_at')
                ->paginate(12, ['*'], $pageName, $legacyPage)->appends([...$request->except(['page']), 'tab' => $kind])->fragment('staff-tab-content');
        }
        return view('site.page', ['page' => $page, 'path' => $request->getPathInfo(), 'title' => $customPage?->title ?? $item?->title ?? ($portal->isGovernor() ? $portal->member->name : $portal->title),
            'county' => null, 'home' => $home, 'settings' => $home['settings'] ?? [], 'navigation' => [],
            'view' => 'staff-portal', 'portal' => $portal, 'staffItem' => $item, 'staffPage' => $customPage, 'staffTab' => $tab,
            'staffNavigation' => StaffPages::navigation($portal), 'staffSlides' => $slides, 'staffFeeds' => $feeds,
            'staffAboutLabel' => $portal->isGovernor() ? 'معرفی فرماندار' : 'معرفی واحد',
            'staffPhotos' => $portal->items()->published()->where('kind', 'gallery')->with('asset')->orderBy('priority')->latest('published_at')->paginate(8, ['*'], 'gallery_page')->withQueryString()->fragment('staff-gallery'),
            'staffVideos' => $portal->items()->published()->where('kind', 'video')->with('asset')->orderBy('priority')->latest('published_at')->paginate(8, ['*'], 'video_page')->withQueryString()->fragment('staff-videos'),
            'staffLinks' => $portal->items()->published()->where('kind', 'link')->with('asset')->orderBy('priority')->get()]);
    }
}
