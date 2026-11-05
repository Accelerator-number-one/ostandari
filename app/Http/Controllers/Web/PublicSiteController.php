<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\CommunityController;
use App\Models\Organization;
use App\Services\PortalView;
use App\Services\ServiceAgreementPages;
use App\Services\Html;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicSiteController
{
    public function __invoke(Request $request, PortalView $ui, PublicController $public, ?string $path = null)
    {
        $path = '/'.trim($path ?? '', '/');
        abort_if($path === '/admin' || str_starts_with($path, '/admin/'), 404);
        if($legacyCounty=$ui->county($request->query('name'))){
            $legacyQuery=$request->except(['name','section']);
            $legacyPath=$path==='/office'?(($request->query('section')==='about')?'/about':'/'):$path;
            return redirect($ui->href($legacyPath,$legacyCounty).($legacyQuery?'?'.http_build_query($legacyQuery):''));
        }
        $county = $ui->county(explode('/', trim($path, '/'))[0] ?? null);
        if ($county) {
            $first = explode('/', trim($path, '/'))[0];
            if ($first !== $county['slug']) return redirect('/'.$county['slug'].substr($path, strlen($first)+1).($request->getQueryString() ? '?'.$request->getQueryString() : ''));
            $path = substr($path, strlen($county['slug'])+1) ?: '/';
        }
        if ($path === '/office' && $ui->county($request->query('name'))) return redirect('/'.$ui->county($request->query('name'))['slug']);
        if ($path === '/county') return redirect('/counties');
        if ($path === '/link') return redirect($ui->href('/links', $county));
        if ($path === '/blank' && $request->query('id') === '57') return redirect($ui->href('/install', $county));
        if ($path === '/manager') return redirect($ui->href('/managers?group='.urlencode($request->query('member_group', '1')), $county));

        $meta = $ui->data('frontend');
        abort_if(\App\Services\RetiredSections::path($path), 404);
        $section = $ui->section($path);
        $template = collect($meta['templates'])->first(fn($p)=>$p['group']==='site' && $p['path']===$path);
        $extra = ['/services','/services/provincial','/links','/gallery','/forms','/forum','/governors','/deputies','/transparency','/governorate','/duties','/sitemap','/sitemap.xml','/rss','/about/general','/districts','/units','/accessibility','/performance','/participation','/service-agreement/statement','/polls/site','/announcements','/resolutions','/documents','/password','/verify'];
        $dynamic = preg_match('~^/(news|content|duties|service|forms|forum)/([^/]+)$~', $path, $match);
        $known = $path==='/' || isset($meta['publicTitles'][$path]) || $section || $template || $dynamic || in_array($path, $extra, true) || in_array($path, ['/en','/ar'], true);
        $customPage = $known ? null : \App\Services\CustomPages::resolve($path, $county);
        abort_unless($known || $customPage, 404);
        $title = $county && $path==='/' ? 'فرمانداری شهرستان '.$county['name'] : ($meta['publicTitles'][$path] ?? $section['title'] ?? 'پرتال استانداری مازندران');
        $page = ($county ? collect($meta['templates'])->firstWhere('path','/county') : $template) ?? collect($meta['templates'])->firstWhere('path','/') ?? [];
        $page = [...$page, 'id'=>'BladePublicPage', 'path'=>$request->getPathInfo(), 'resource'=>'', 'serverRendered'=>true, 'controller'=>'site'];
        $query = $request->query();
        $query['locale'] = in_array($path, ['/en','/ar'], true) ? substr($path,1) : 'fa';
        $page['lang'] = $query['locale'];
        if(in_array($path,['/en','/ar'],true)){
            $page=[...$template,'controller'=>'site','serverRendered'=>false];
            return view('site.foreign-page',['page'=>$page,'title'=>$template['title']]);
        }
        $organization = $county ? Organization::active()->with(['county','aerial','banner'])->where('type','county')->whereHas('county', fn($q)=>$q->whereIn('slug', \App\Support\CountySlugs::variants($county['slug'])))->first() : null;
        if ($county && $organization) $query['organization_id'] = $organization->id;
        $r = $request->duplicate($query);
        $home = $county && !$organization ? ['settings'=>$public->settings()->getData(true)['data'], 'menus'=>[]] : $public->home($r, $organization?->slug)->getData(true)['data'];
        if (config('website.snapshot') && !$county) {
            $snapshot = $ui->data('official-site');
            if (empty($home['news'])) $home['news'] = array_map(fn($n)=>[...$n,'id'=>$n['slug']], array_filter($snapshot['news'],fn($n)=>$n['type']==='news'));
            if (empty($home['slides'])) $home['slides'] = $snapshot['slides'];
            if (empty($home['governor'])) $home['governor'] = $snapshot['governor'];
        }
        $data = ['page'=>$page,'path'=>$path,'title'=>$title,'county'=>$county,'organization'=>$organization?->toArray(),'home'=>$home,'settings'=>$home['settings'] ?? [],'navigation'=>$ui->navigation($home['menus'] ?? [], $county, $home['custom_pages'] ?? []),'section'=>$section,'items'=>[],'meta'=>null,'view'=>'generic'];
        $countyPage = $county && $organization && isset(\App\Services\CountyPortal::pages()[$path])
            ? \App\Models\Content::visible()->where('organization_id',$organization->id)->where('locale',$query['locale'])->where('type','page')->where('metadata->portal_path',$path)->latest('updated_at')->first() : null;
        $agreementKey = $county ? null : ServiceAgreementPages::keyForPath($path);
        $dutySource = \App\Services\UnitDuties::sourceForPath($path);
        $dutyRecord = $dutySource ? \App\Services\UnitDuties::importedPage($path, $home['organization']['id'] ?? null) : null;
        $dutyReport = $dutySource && !$county ? \App\Services\UnitDuties::report() : null;
        if ($customPage) {
            $data['customPage'] = $customPage->toArray();
            $data['title'] = $customPage->title;
            $data['view'] = 'custom-page';
        } elseif ($agreementKey) {
            $rootId = $home['organization']['id'] ?? null;
            abort_unless($rootId, 404);
            $agreement = ServiceAgreementPages::query($agreementKey, $rootId)->visible()->firstOrFail();
            $data['content'] = $agreement->toArray();
            $data['agreementKey'] = $agreementKey;
            $data['title'] = $agreement->title;
            $data['view'] = 'service-agreement';
        } elseif ($dutyRecord || $dutySource && $dutyReport && (isset($dutyReport['content_ids'][$dutySource['source_id']]) || in_array($dutySource['source_id'], $dutyReport['skipped_ids'], true))) {
            abort_unless($dutyRecord, 404);
            $data += $public->content($r, $dutyRecord->id)->getData(true)['data'];
            $data['title'] = $dutyRecord->title;
            $data['view'] = 'duty';
        } elseif ($path === '/duties' || ($dutyGroup = \App\Services\UnitDuties::groupForPath($path))) {
            $result = $public->contents($r->duplicate([...$query, 'per_page' => 100]), 'duty')->getData(true);
            $data['items'] = $county && !$organization ? [] : $result['data'];
            if (!empty($dutyGroup)) $data['items'] = array_values(array_filter($data['items'], fn ($unit) => ($unit['duty_group'] ?? null) === $dutyGroup));
            $data['meta'] = $result['meta'];
            $data['view'] = 'duties';
        } elseif ($countyPage) {
            $data += $public->content($r,$countyPage->id)->getData(true)['data'];
            $data['title']=$countyPage->title;$data['view']='article';
        } elseif ($path==='/' || in_array($path,['/en','/ar'],true)) {
            $data['view']=$county?'county-home':'home';
        } elseif($county && $path==='/about') {
            $data['view']='county-home';
        } elseif ($dynamic && in_array($match[1], ['content','news','duties'],true)) {
            try {
                $payload = ctype_digit($match[2]) ? $public->content($r, (int)$match[2])->getData(true)['data'] : $public->contentSlug($r,$match[1]==='duties'?'duty':'news',$match[2])->getData(true)['data'];
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $snapshot = config('website.snapshot') ? collect($ui->data('official-site')['news'])->firstWhere('slug',$match[2]) : null;
                if (!$snapshot) throw $e;
                $payload = ['content'=>[...$snapshot,'id'=>$snapshot['slug'],'body'=>'<p>'.e($snapshot['excerpt'] ?? '').'</p>'],'related'=>[],'comments'=>[]];
            }
            abort_if($county && ($payload['content']['organization_id'] ?? null) !== $organization?->id, 404);
            abort_if($match[1]==='duties' && ($payload['content']['type'] ?? '')!=='duty',404);
            $data += $payload;
            $data['title']=$payload['content']['title'];$data['view']=($payload['content']['type']??'')==='duty'?'duty':'article';
        } elseif ($section && ($section['kind'] ?? '')==='article') {
            $payload = $public->contentSlug($r, $section['record_type'] ?? 'page', $section['record_slug'] ?? 'official-page-'.$section['id'])->getData(true)['data'];
            abort_if($county && ($payload['content']['organization_id']??null)!==$organization?->id,404);
            $data += $payload;$data['title']=$payload['content']['title'];$data['view']='article';
        } else {
            $types=['/news'=>'news','/announcements'=>'announcement','/resolutions'=>'resolution','/documents'=>'document','/regulations'=>'document','/investments'=>'publication','/tenders'=>'tender','/contracts'=>'contract','/calls'=>'call','/events'=>'event','/duties'=>'duty'];
            $kind=$section['kind'] ?? '';
            if (isset($types[$path]) || in_array($kind,['news','documents','tenders'],true)) {
                $result=$public->contents($r,$types[$path] ?? ['news'=>'news','documents'=>'document','tenders'=>'tender'][$kind])->getData(true);
                $data['items']=$county&&!$organization ? [] : $result['data'];$data['meta']=$result['meta'];$data['view']=$path==='/duties'?'duties':($path==='/events'?'events':'collection');
            } elseif (in_array($path,['/managers','/governors','/deputies'],true) || in_array($kind,['managers','governors'],true)) {
                $group=(string)($section['group'] ?? $request->query('group',$path==='/deputies'?'2':'1'));
                $memberKind=$path==='/governors'||$kind==='governors'?'governor':($group==='4'?'county_governor':($group==='6'?'advisor':'manager'));
                if ($county) $memberKind=$path==='/governors'?'county_governor':$memberKind;
                if($request->filled('person')){
                    $request->validate(['person'=>'required|integer|min:1']);
                    $profileQuery=array_diff_key($query,array_flip(['kind','member_group','group','page','per_page','category_id','county_id','q','person']));
                    $person=$public->catalogShow($r->duplicate($profileQuery),$request->integer('person'),'members')->getData(true)['data'];
                    $owner=$organization?->id??($home['organization']['id']??null);
                    abort_unless(($person['organization_id']??null)===$owner || !$county && empty($person['organization_id']),404);
                    $data['items']=[$person];$data['title']=$person['name'];
                }else{
                    $memberQuery=[...$query,'kind'=>$request->query('kind',$memberKind),'per_page'=>24];
                    if($memberKind!=='governor' && !($county && $path==='/governors'))$memberQuery['member_group']=$group;
                    $result=$county&&!$organization?['data'=>[],'meta'=>null]:$public->catalog($r->duplicate($memberQuery),'members')->getData(true);
                    $data['items']=$result['data'];$data['meta']=$result['meta'];
                    if($path!=='/governors')$data['title']=collect($meta['managerGroups'])->firstWhere('group',$group)['title']??$data['title'];
                }
                $data['view']='members';
                if (!$county && !$request->filled('person') && $memberKind !== 'governor' && isset(\App\Services\ManagerPages::GROUPS[$group])) {
                    $data['directoryGroup'] = $group;
                }
                if (!$county && !$request->filled('person') && $group === '5') {
                    $data['directoryPage'] = \App\Services\ManagerPages::page($home['organization']['id'] ?? null);
                    if ($data['directoryPage']) $data['title'] = $data['directoryPage']->title;
                }
            } elseif ($path === '/links' || $kind === 'links') {
                $data['items'] = \App\Services\LocalLinks::directory($county ? $organization?->id : ($home['organization']['id'] ?? null), $query['locale'], !$county);
                $data['items'] = [...$data['items'], ...$ui->customLinks($home, 'links', $county)];
                $data['linkGroups'] = \App\Services\LocalLinks::groups($data['items']);
                $data['resource'] = 'links';
                $data['view'] = 'links-directory';
                $data['title'] = 'پیوندها';
            } elseif (in_array($path,['/services','/services/provincial','/links','/gallery','/forms','/faq','/polls','/polls/site'],true) || in_array($kind,['services','links','faqs','polls'],true) || ($dynamic && in_array($match[1],['service','forms'],true))) {
                $resource=$dynamic ? ($match[1]==='service'?'services':'forms') : (['/gallery'=>'galleries','/faq'=>'faqs','/services/provincial'=>'services','/polls/site'=>'polls'][$path] ?? ltrim($path,'/'));
                if ($section && in_array($kind,['services','links','faqs','polls'],true)) $resource=$kind;
                $id=$dynamic?(int)$match[2]:($request->integer('id') ?: $request->integer('album'));
                $data['items']=$county&&!$organization?[]:($id?[$public->catalogShow($r,$id,$resource)->getData(true)['data']]:$public->catalog($r->duplicate([...$query,'per_page'=>100]),$resource)->getData(true)['data']);
                if ($county) $data['items']=array_values(array_filter($data['items'],fn($x)=>!isset($x['organization_id'])||$x['organization_id']===$organization?->id));
                $data['resource']=$resource;$data['view']='catalog';
                if ($resource==='links') $data['items']=[...$ui->usefulLinks($data['items']), ...$ui->customLinks($home,'links',$county)];
            } elseif ($path==='/transparency' || ($section['id']??null)==='menu-195') {
                $data['view']='transparency';$data['transparent']=['tender'=>$public->contents($r,'tender')->getData(true)['data'],'contract'=>$public->contents($r,'contract')->getData(true)['data']];
            } elseif ($path==='/search') {
                $data['results']=$request->filled('q') ? $public->search($r)->getData(true)['data'] : [];$data['view']='search';
            } elseif (in_array($path,['/install','/download'],true)) {$data['view']='install';
            } elseif ($path==='/counties') {$data['view']='counties';
            } elseif ($path==='/quick-access') {
                $data['items']=array_map(fn($destination)=>['href'=>$destination,'title'=>$meta['publicTitles'][$destination]??'خدمات الکترونیکی'],['/news','/counties','/services','/services/provincial','/contact','/track','/managers','/duties','/gallery','/transparency','/regulations','/faq','/polls','/links','/citizen']);
                $data['items']=[...$data['items'], ...$ui->customLinks($home,'quick',$county)];
            } elseif (in_array($path,['/regulations','/privacy'],true)) {$data['view']='terms';
            } elseif (in_array($path,['/contact','/contact-us','/ideas','/support'],true)) {$data['view']='contact';
            } elseif ($path==='/assistant') {$data['view']='assistant';
            } elseif ($path==='/sitemap' || $path==='/sitemap.xml') {$data['view']='sitemap';
            } elseif ($path==='/rss') {
                $news=$home['news']??[];
                return response(view('site.rss',['items'=>$news,'ui'=>$ui]))->header('Content-Type','application/rss+xml; charset=UTF-8');
            } elseif ($path==='/forum' || $kind==='forum' || ($dynamic&&$match[1]==='forum')) {
                $community=app(CommunityController::class);
                $data['forum']=$dynamic?$community->topic((int)$match[2])->getData(true)['data']:$community->topics($r)->getData(true)['data'];$data['view']='forum';$data['forumCategories']=\App\Models\Category::active()->where('type','forum')->get(['id','name'])->toArray();
            } elseif ($path==='/forgot-password' || $path==='/password' || $path==='/verify') {$data['view']='recovery';
            } elseif ($section) {
                $data['items']=\App\Services\RetiredSections::entries(collect($ui->data('official-site')['pages'])->whereIn('id',$section['children']??[])->values()->all());
                if (empty($data['items'])) {
                    try {$candidate=$public->contentSlug($r,'page','official-page-'.$section['id'])->getData(true)['data']['content'];if(!$county||($candidate['organization_id']??null)===$organization?->id){$data['content']=$candidate;$data['view']='article';$data['related']=[];$data['comments']=[];}} catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {}
                }
            }
        }
        if ($path==='/sitemap.xml') {
            $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach([...$meta['officialSections'], ...array_map(fn($p)=>['href'=>$p['path']],$home['custom_pages']??[])] as $entry){if(\App\Services\RetiredSections::entry($entry))continue;$href=$ui->href($entry['href'],$county);if(str_starts_with($href,'/')&&!str_starts_with($href,'//'))$xml.='<url><loc>'.htmlspecialchars(url($href),ENT_XML1,'UTF-8').'</loc></url>';}
            return response($xml.'</urlset>')->header('Content-Type','application/xml; charset=UTF-8');
        }
        return view('site.page',$data);
    }
}
