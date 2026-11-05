<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Presentation helpers shared by the Blade public and management views. */
final class PortalView
{
    private array $files = [];

    public function data(string $name): array
    {
        if (isset($this->files[$name])) return $this->files[$name];
        $data=json_decode(file_get_contents(resource_path('data/'.$name.'.json')), true, 512, JSON_THROW_ON_ERROR);
        if ($name==='frontend') {
            $data['officialSections']=RetiredSections::entries($data['officialSections']);
            $data['officialNavigation']['main']=RetiredSections::entries($data['officialNavigation']['main']);
            foreach(array_keys($data['publicTitles']) as $path)if(RetiredSections::path($path))unset($data['publicTitles'][$path]);
        }
        return $this->files[$name]=$data;
    }

    public function county(?string $name): ?array
    {
        $aliases = ['noor'=>'nur', 'nour'=>'nur', 'chalus'=>'chalus', 'chalous'=>'chalus', 'nowshahr'=>'noshahr', 'mahmoudabad'=>'mahmudabad', 'mahmoodabad'=>'mahmudabad', 'fereydounkenar'=>'fereydunkenar', 'joybar'=>'juybar', 'miandorood'=>'miandorud', 'ghalugah'=>'galugah', 'galoogah'=>'galugah', 'ghaemshahr'=>'qaemshahr', 'north-savadkuh'=>'savadkuh-shomali', 'gov_tonekabon'=>'tonekabon'];
        $slug = $aliases[$name ?? ''] ?? $name;
        return collect($this->data('frontend')['counties'])->first(fn ($county) => $county['slug'] === $slug || $county['name'] === $name);
    }

    public function href(?string $path, ?array $county = null): string
    {
        $path = $path ?: '/';
        $path = ManagerPages::internalHref($path) ?? $path;
        $path = LocalLinks::destination($path) ?? $path;
        $path = LegacyLinkPages::destination($path) ?? $path;
        if (preg_match('~^(https?://|mailto:|tel:)~i', $path)) return $path;
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\\\\\x00-\x1f\x7f]/', $path)) return '#';
        if(parse_url($path,PHP_URL_PATH)==='/office'){
            parse_str(parse_url($path,PHP_URL_QUERY) ?? '',$query);
            if($destination=$this->county($query['name']??null)) return '/'.$destination['slug'].(($query['section']??null)==='about'?'/about':'');
        }
        if (! $county || preg_match('~^/(login|register|citizen|panel|county-admin|en|ar|forgot-password|password|counties|design|fonts|styles|build|storage|page-assets|api|web-api|session|imported-pages)(/|\?|$)~', $path)) return $path;
        if($this->county(explode('/',ltrim(parse_url($path,PHP_URL_PATH),'/'))[0]??null)) return $path;
        if (str_starts_with($path, '/'.$county['slug'].'/') || $path === '/'.$county['slug']) return $path;
        $path = preg_replace('~^/office(?=\?|$)~', '/', $path);
        return '/'.$county['slug'].($path === '/' ? '' : $path);
    }

    public function usefulLinks(array $items = []): array
    {
        $links = array_values(array_filter(RetiredSections::entries($items), fn ($item) => in_array($item['position'] ?? 'useful', ['useful', 'quick'], true)));
        if ($links) return $links;
        $source = $this->data('official-site');
        return array_values(array_filter(RetiredSections::entries($source['pages']), fn ($item) => in_array($item['id'], $source['useful'], true)));
    }

    public function image(mixed $record): ?string
    {
        if (is_array($record)) {
            foreach (['cover', 'image', 'banner', 'image_url'] as $key) {
                if (isset($record[$key])) {
                    $image = $this->image($record[$key]);
                    if ($image) return $image;
                }
            }
            if($image=$this->image($record['metadata']['design_image'] ?? null)) return $image;
            if(isset($record['url']) && (str_starts_with($record['mime']??'', 'image/') || (!isset($record['title']) && !isset($record['name'])))) return $this->image($record['url']);
            $source=$this->data('official-site');
            if(($record['name']??null) === ($source['governor']['name']??null)) return $this->image($source['governor']['image']);
            foreach($source['slides'] as $slide) if(!empty($record['title']) && $record['title']===$slide['title']) return $this->image($slide['image']);
            return null;
        }
        return is_string($record) && preg_match('~^(https?://|/(?!/))~i', $record) ? $record : null;
    }

    public function text(mixed $value, int $limit = 0): string
    {
        $value = trim(html_entity_decode(strip_tags(is_scalar($value) ? (string) $value : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $limit ? Str::limit($value, $limit) : $value;
    }

    public function date(?string $value): string
    {
        if (! $value) return '';
        try {
            $formatter = new \IntlDateFormatter('fa_IR@calendar=persian', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'Asia/Tehran', \IntlDateFormatter::TRADITIONAL, 'yyyy/MM/dd');
            return $formatter->format(new \DateTimeImmutable($value));
        } catch (\Throwable) { return $value; }
    }

    public function article(array $record, ?array $county = null): string
    {
        if (!$county && ($record['organization']['type'] ?? null) === 'county') {
            $county = $this->county($record['organization']['county']['slug'] ?? null);
        }
        return $this->href((($record['type'] ?? null)==='duty' ? '/duties/' : '/content/').rawurlencode((string) $record['id']), $county);
    }

    public function adminHref(string $path, array $context): string
    {
        return $context['type'] === 'county'
            ? preg_replace('~^/panel/admin(?=/|\?|$)~', $context['dashboard_path'], $path)
            : $path;
    }

    public function menu(array $menus, string $key): ?array
    {
        $menu = collect($menus)->firstWhere('key', $key);
        return $menu ? [...$menu, 'items' => RetiredSections::entries($menu['items'] ?? [])] : null;
    }

    public function countyTitle(string $title, ?array $county): string
    {
        if(!$county) return $title;
        $title=str_replace(['آشنایی با مازندران','مدیران استان','استانداری مازندران'],['آشنایی با '.$county['name'],'مدیران شهرستان','فرمانداری '.$county['name']],$title);
        $title=preg_replace('/استاندار(?:ان)?(?: ادوار)?/u','فرماندار',$title);
        return str_replace(['معاونین فرماندار مازندران','فرمانداران مازندران','مدیران روابط عمومی استان','دستگاه‌های استان','دستگاه های اجرایی استان','مازندران'],['معاونین فرماندار','بخشداران و دهیاران','مدیران روابط عمومی شهرستان','دستگاه‌های شهرستان','دستگاه‌های اجرایی شهرستان',$county['name']],$title);
    }

    public function navigation(array $menus, ?array $county = null, array $customPages = []): array
    {
        $menu = collect($menus)->firstWhere('key', 'official-main');
        $convert = function (array $items) use (&$convert, $county): array {
            return array_map(fn ($item) => [
                'id' => (string) $item['id'], 'title' => $item['title'],
                'href' => $this->href(!empty($item['content_id']) ? '/content/'.$item['content_id'] : ($item['url'] ?? $item['href'] ?? '/'), $county),
                'children' => $convert($item['children'] ?? []),
            ], $items);
        };
        $items = $menu ? $convert($menu['items'] ?? []) : $this->data('frontend')['officialNavigation']['main'];
        $scope = function (array $items) use (&$scope, $county): array {
            if($county)$items=array_values(array_filter($items,fn($item)=>!str_contains($item['href'],'/office?')));
            return array_map(fn ($item) => [...$item, 'title'=>$this->countyTitle($item['title'],$county), 'href' => $this->href($item['href'], $county), 'children' => $scope($item['children'] ?? [])], $items);
        };
        $items = RetiredSections::entries($scope($items));
        foreach ($items as &$item) {
            $path = parse_url($item['href'], PHP_URL_PATH);
            if ($county) $path = preg_replace('~^/'.preg_quote($county['slug'], '~').'(?=/|$)~', '', $path);
            $placement = ['/services' => 'services', '/about' => 'about'][$path] ?? null;
            if ($placement) $item['children'] = [...($item['children'] ?? []), ...$this->customLinks(['custom_pages' => $customPages], $placement, $county)];
        }
        unset($item);
        return $items;
    }

    public function customLinks(array $home, string $placement, ?array $county = null): array
    {
        return RetiredSections::entries(array_values(array_map(fn ($page) => ['id' => 'custom-page-'.$page['id'], 'title' => $page['link_title'] ?: $page['title'],
            'href' => $this->href($page['path'], $county), 'url' => $this->href($page['path'], $county), 'custom_page' => true,
            'link_style' => $page['link_style'], 'image' => $page['link_style'] === 'image' ? ($page['link_image'] ?? null) : null,
            'children' => []], array_filter($home['custom_pages'] ?? [], fn ($page) => in_array($placement, $page['placements'] ?? [], true)))));
    }

    public function section(string $path): ?array
    {
        return collect($this->data('frontend')['officialSections'])->first(fn ($section) => parse_url($section['href'], PHP_URL_PATH) === $path);
    }

    public function membersForGroup(array $people, string $group): array
    {
        return array_values(array_filter($people, function ($person) use ($group) {
            if (($person['kind']??'')==='county_governor') return $group==='4';
            if (($person['kind']??'')==='advisor') return $group==='6';
            if (!empty($person['kind']) && $person['kind']!=='manager') return false;
            $slug=$person['category']['slug']??'';
            if(preg_match('/menu-(61|64|62|63|198|109)$/',$slug,$m)) return ['61'=>'1','64'=>'2','62'=>'3','63'=>'4','198'=>'5','109'=>'6'][$m[1]]===$group;
            $label=str_replace(['ي','ك','‌'],['ی','ک',' '],($person['category']['name']??'').' '.($person['position']??''));
            $inferred=preg_match('/روابط\s*عمومی/u',$label)?'5':(str_contains($label,'معاون')?'2':(preg_match('/دستگاه|ادارات/u',$label)?'3':'1'));
            return $inferred===$group;
        }));
    }

    public function moduleAllowed(array $module, \App\Models\User $user): bool
    {
        if (!empty($module['custom_page_builder'])) return CustomPages::mayManage($user);
        if (isset($module['service_agreement']) && !$user->hasGlobalAccess() && $user->organization?->type !== 'governorate') return false;
        if (!empty($module['manager_directory_page']) && !$user->hasGlobalAccess() && $user->organization?->type !== 'governorate') return false;
        if ($module['path'] === '/panel/admin/annual-slogan') return Workspace::mayManageAnnualSlogan($user);
        if (!$user->hasPermission($module['permission'])) return false;
        if (Workspace::context($user)['type'] === 'county' && in_array($module['path'], ['/panel/admin/governors','/panel/admin/counties','/panel/admin/settings','/panel/admin/item-order','/panel/admin/roles','/panel/admin/internal-users','/panel/admin/external-users','/panel/admin/subscribers'], true)) return false;
        return true;
    }
}
