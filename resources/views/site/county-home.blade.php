@php
    $record=$organization['county'] ?? [];
    $governor=$home['governor'] ?? [];
    $photo=$ui->image($governor['image'] ?? null);
    $people=collect($home['members']??[])->except('county_governor')->flatten(1)->all();
    $about=$path==='/about';
    $portal=$home['county_portal'] ?? [];
    $sections=$portal['sections'] ?? array_fill_keys(array_keys(\App\Services\CountyPortal::SECTIONS),true);
    $labels=$portal['titles'] ?? \App\Services\CountyPortal::TITLES;
    $showGovernor=!$about&&$sections['governor']&&!empty($governor['name']);
    $showSlider=!$about&&$sections['slider'];
    $quickMenu=$ui->menu($home['menus']??[],'portal-quick');
    $shortcuts=$quickMenu['items'] ?? [['title'=>'میز خدمت الکترونیکی','url'=>'/services'],['title'=>'ارتباط با فرمانداری','url'=>'/contact'],['title'=>'پیگیری درخواست‌ها','url'=>'/citizen']];
    $quickLinks=[...\App\Services\RetiredSections::entries($shortcuts), ...$ui->customLinks($home,'quick',$county)];
    $usefulLinks=[...\App\Services\RetiredSections::entries($home['links']??[]), ...$ui->customLinks($home,'links',$county)];
    $leadNews=$showSlider&&$sections['news'];
    $leadShortcuts=$showSlider&&!$showGovernor&&$sections['shortcuts']&&count($quickLinks)>0;
    $showMain=$about||(!$leadNews&&$sections['news'])||$sections['gallery'];
    $showSidebar=($sections['managers']&&count($people)>0)||($sections['shortcuts']&&!$leadShortcuts&&count($quickLinks)>0)||$sections['newsletter']||($sections['links']&&count($usefulLinks)>0);
@endphp
<div class="source-office portal-county-office portal-county-home" data-county-office="{{ $county['slug'] }}" data-office-view="{{ $about?'about':'home' }}">
    @if(!$about)
        <h1 class="portal-visually-hidden">{{ $portal['title'] ?? 'فرمانداری شهرستان '.$county['name'] }}</h1>
        @if($showSlider||$showGovernor)
        <div class="portal-county-intro {{ $leadNews?'has-news':'' }} {{ $showGovernor||$leadShortcuts?'has-side-panel':'' }} {{ $showGovernor?'with-governor':'' }} {{ !$showSlider?'governor-only':'' }}">
            @if($leadNews)
                @include('site.county-news',['compactCountyNews'=>true])
            @endif
            @if($showSlider)
                <div class="county-home-slideshow">@include('site.slider',['emptyCountyFallback'=>true])</div>
            @endif
            @if($showGovernor)
                <aside class="portal-office-governor" aria-label="فرماندار {{ $county['name'] }}">
                    <div class="portal-office-portrait"><img src="{{ $photo ?? '/design/site/assets/image/logo.png' }}" alt="{{ $governor['name'] }}" width="144" height="168"></div>
                    <h2>{{ $governor['name'] }}</h2>
                    <p>{{ $governor['position'] ?? 'فرماندار شهرستان '.$county['name'] }}</p>
                    <div class="portal-office-actions">
                        <a class="portal-office-button" href="{{ $ui->href('/contact?type=normal',$county) }}">ارتباط با فرماندار</a>
                        <a class="portal-office-button is-outline" href="{{ $ui->href('/about',$county) }}">معرفی شهرستان</a>
                    </div>
                </aside>
            @elseif($leadShortcuts)
                @include('site.county-shortcuts',['leadCountyShortcuts'=>true])
            @endif
        </div>
        @endif
    @endif

    @if($sections['aerial']&&($aerial=$ui->image($organization['aerial'] ?? null)))
        <figure class="county-aerial-panel"><img src="{{ $aerial }}" alt="تصویر هوایی شهرستان {{ $county['name'] }}" loading="lazy"><figcaption>تصویر هوایی شهرستان {{ $county['name'] }}</figcaption></figure>
    @endif
    @if(!$about&&($sections['advertisements']??true)&&!empty($home['advertisements']))
        <section class="portal-county-advertisements" aria-label="بنرهای اطلاع‌رسانی">
            @foreach($home['advertisements'] as $banner)
                <a href="{{ $ui->href(!empty($banner['content_id'])?'/content/'.$banner['content_id']:($banner['url']??'/news'),$county) }}">
                    @if($image=$ui->image($banner))<img src="{{ $image }}" alt="{{ $banner['title']??'' }}" loading="lazy">@else<span>{{ $banner['title']??'' }}</span>@endif
                </a>
            @endforeach
        </section>
    @endif
    @if(!$about)@include('site.custom-page-banners')@endif

    @if($showMain||$showSidebar)
    <div class="portal-office-content-grid {{ $showMain?'has-main':'' }} {{ $showSidebar?'has-sidebar':'' }}">
        @if($showMain)
        <div class="portal-office-main-column">
            @if($about)
                <section class="portal-office-panel portal-office-about">
                    <header class="portal-office-panel-head"><h2>دربارهٔ شهرستان {{ $county['name'] }}</h2></header>
                    <div class="portal-office-panel-body">
                        <div class="portal-rich-text">{!! \App\Services\Html::clean($portal['about_body'] ?? $record['description'] ?? '') !!}</div>
                        @if(!empty($record['video_url']))<a class="portal-office-button" href="{{ $record['video_url'] }}" target="_blank" rel="noopener">ویدئوی معرفی شهرستان ↗</a>@endif
                    </div>
                </section>
            @elseif($sections['news']&&!$leadNews)
                @include('site.county-news',['compactCountyNews'=>false])
            @endif
            @if($sections['gallery'])
                <section class="portal-office-panel">
                    <header class="portal-office-panel-head"><h2>{{ $labels['gallery'] }}</h2><a href="{{ $ui->href('/gallery',$county) }}">مشاهده همه ←</a></header>
                    <div class="portal-office-panel-body">
                        <div class="portal-office-gallery">
                            @forelse($home['galleries']??[] as $album)
                                <a href="{{ $ui->href('/gallery?id='.$album['id'],$county) }}">
                                    <div class="portal-office-gallery-image">@if($image=$ui->image($album['cover'] ?? $album['items'][0]['image'] ?? null))<img src="{{ $image }}" alt="" width="420" height="270" loading="lazy">@endif</div>
                                    <h3>{{ $album['title'] }}</h3>
                                </a>
                            @empty
                                <p class="source-empty">تصویری منتشر نشده است.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            @endif
        </div>
        @endif

        @if($showSidebar)
        <aside class="portal-office-sidebar" aria-label="اطلاعات و خدمات فرمانداری {{ $county['name'] }}">
            @if($sections['managers']&&count($people)>0)
                <section class="portal-office-panel"><header class="portal-office-panel-head"><h2>{{ $labels['managers'] }}</h2></header><div class="portal-office-panel-body">@include('site.members',['items'=>$people])</div></section>
            @endif
            @if($sections['shortcuts']&&!$leadShortcuts&&count($quickLinks)>0)
                @include('site.county-shortcuts',['leadCountyShortcuts'=>false])
            @endif
            @if($sections['newsletter'])
                <section class="portal-office-panel portal-office-newsletter">
                    <header class="portal-office-panel-head"><h2>{{ $labels['newsletter'] }}</h2></header>
                    <div class="portal-office-panel-body">
                        <form class="portal-form" x-data="apiForm('newsletter/subscribe')" @submit.prevent="submit($event)">
                            <label>ایمیل<input type="email" name="email" required dir="ltr"></label>
                            <button class="portal-office-button" :disabled="busy">عضویت در خبرنامه</button>
                            <p role="status" x-text="message"></p><p role="alert" x-text="error"></p>
                        </form>
                    </div>
                </section>
            @endif
            @if($sections['links']&&count($usefulLinks)>0)
                <section class="portal-office-panel">
                    <header class="portal-office-panel-head"><h2>{{ $labels['links'] }}</h2></header>
                    <div class="portal-office-panel-body"><ul class="portal-office-links">
                        @foreach($usefulLinks as $link)<li><a href="{{ $ui->href($link['url'],$county) }}">@include('partials.link-symbol')<span>{{ $link['title'] }}</span></a></li>@endforeach
                    </ul></div>
                </section>
            @endif
        </aside>
        @endif
    </div>
    @endif

    @if(!$about&&$sections['events'])@include('site.events-home')@endif
    @if($sections['location'])
        <section class="portal-office-locator" aria-label="{{ $county['name'] }} در یک نگاه">
            <div class="portal-office-location-copy"><span class="portal-office-eyebrow">استان مازندران</span><h2>{{ $county['name'] }} <span>در یک نگاه</span></h2><p>{{ $ui->text($record['description'] ?? '') }}</p><a class="portal-office-button" href="{{ $ui->href('/about',$county) }}">دربارهٔ شهرستان {{ $county['name'] }} ←</a></div>
            <div class="portal-office-location-map">@include('site.county-glance-map')</div>
        </section>
    @endif
</div>
