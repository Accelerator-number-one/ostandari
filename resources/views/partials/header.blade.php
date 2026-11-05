@php
    $portalName=$county ? ($home['county_portal']['title'] ?? 'فرمانداری شهرستان '.$county['name']) : ($settings['site.name'] ?? 'استانداری مازندران');
    $countySections=$home['county_portal']['sections'] ?? [];
    $logo=$ui->image($settings['site.logo'] ?? null) ?? '/design/site/assets/image/logo.png';
    $local=fn($href)=>$county ? preg_replace('~^/'.preg_quote($county['slug'],'~').'(?=/|\?|$)~','',$href) ?: '/' : $href;
    $rootLink=fn($href)=>collect($navigation)->first(fn($item)=>$local($item['href'])===$href);
    $categories=array_values(array_filter($navigation,fn($item)=>!in_array($local($item['href']),['/','/transparency','/managers','/duties','/about','/polls'],true)));
    $utility=[['تماس با ما','/contact'],['دسترسی سریع','/quick-access'],['سامانه شفافیت','/transparency'],['قوانین و مقررات','/regulations']];
    if($county && ($utilityMenu=$ui->menu($home['menus']??[],'portal-utility'))!==null)$utility=array_map(fn($item)=>[$item['title'],!empty($item['content_id'])?'/content/'.$item['content_id']:($item['url']??'/')],$utilityMenu['items']??[]);
@endphp
<header class="header-container" data-portal-ui="header-20261024" data-alpine-header="true" data-county-header="{{ $county['slug'] ?? '' }}" x-data="portalHeader" @keydown.escape.window="dismiss()" @click.outside="mega=false;language=false"
    @click="const link=$event.target.closest('a[href]'); if(link && link.origin===location.origin && /^\/(?:[^/]+\/)?assistant\/?$/.test(link.pathname)) { $event.preventDefault(); if(!$refs.assistantNotice.open) $refs.assistantNotice.showModal(); }">
    <a class="source-skip" href="#main-content">رفتن به محتوای صفحه</a>
    <div class="nav-bala">
        <nav class="nav-bala-right" aria-label="پیوندهای بالای سایت">@foreach($utility as [$label,$href])<a href="{{ $ui->href($href,$county) }}">{{ $label }}</a>@endforeach</nav>
        <div class="nav-bala-left">
            <a class="header-install-app" href="{{ $ui->href('/install',$county) }}">@include('icons.app')<span>نصب اپلیکیشن استان یار</span></a>
            <div class="iran-container"><div class="iran-img">
                <button type="button" id="flagSwitcher" aria-label="انتخاب زبان" aria-controls="flagDropdown" :aria-expanded="language" @click="language=!language">@include('icons.iran')</button>
                <div class="flag-dropdown" :class="{'active':language}" x-show="language" x-cloak id="flagDropdown">@include('partials.language-options')</div>
            </div><a class="search" href="{{ $ui->href('/search',$county) }}" aria-label="جستجو در سایت">@include('icons.search')</a></div>
            <div class="sabtnam-container"><div class="sabtnan"><a href="/register">ثبت‌نام شهروندان</a></div><div class="vorod"><a href="{{ auth()->check() && !auth()->user()->isStaff() ? '/citizen' : '/login' }}">{{ auth()->check() && !auth()->user()->isStaff() ? 'پنل شهروندی' : 'ورود شهروندان' }}</a></div></div>
        </div>
    </div>
    <div class="imageeee {{ $county ? 'county-portal-banner' : '' }}" data-portal-banner="true">
        @if($county)
            @include('partials.county-banner')
        @else
            <img class="icx-responsive-img" src="{{ $ui->image($settings['site.banner'] ?? null) ?? '/design/site/assets/image/baner.png' }}" width="1380" height="180" alt="{{ $portalName }}" fetchpriority="high">
        @endif
        <div class="shoar-sal"><img src="{{ $ui->image($settings['site.slogan'] ?? null) ?? '/design/county-banners/annual-slogan.png' }}" alt="شعار سال"></div>
    </div>
    <div class="top-header"><div class="header-box" :data-mega-open="mega ? 'true' : null">
        <button type="button" class="header-hamburger" id="hamburgerBtn" aria-label="باز کردن فهرست سایت" :aria-expanded="mobile" aria-controls="mobileMenu" @click="mobile=true"><span></span><span></span><span></span></button>
        <div class="hum-logo"><a href="{{ $ui->href('/',$county) }}"><img src="{{ $logo }}" width="76" height="82" alt="{{ $portalName }}"></a></div>
        <nav class="desktop-menu" aria-label="منوی اصلی">
            <div class="menu-right">
                <div class="menu-item"><div class="rast">@include('icons.home')<a href="{{ $ui->href('/',$county) }}">صفحه اصلی</a></div></div>
                @if($countySections['nav_duties']??true)<div class="menu-item"><div class="rast">@include('icons.duties')<a href="{{ $ui->href('/duties',$county) }}">شرح وظایف واحدها</a></div></div>@endif
                @if($categories)<div class="menu-item has-mega source-mega" :class="{'is-open':mega}" @mouseenter="openMega()" @mouseleave="closeMega()" @focusin="openMega()" @focusout="if(!$el.contains($event.relatedTarget))mega=false">
                    <div class="rast">@include('icons.services')<button type="button" id="khadmat" :aria-expanded="mega" aria-controls="source-services-menu" @click="openMega()" @keydown.arrow-down.prevent="openMega();$nextTick(()=>$refs.firstCategory?.focus())">خدمات <svg class="source-mega-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 9 6 6 6-6"/></svg></button></div>
                    <div class="mega-menu" id="source-services-menu" role="region" aria-label="خدمات و بخش‌های {{ $portalName }}" x-show="mega" x-cloak>
                        <ul class="categories">@foreach($categories as $i=>$item)<li :class="{'active':category==={{ $i }}}" @mouseenter="category={{ $i }}"><button type="button" @if($i===0)x-ref="firstCategory"@endif @focus="category={{ $i }}" @click="category={{ $i }}" :aria-expanded="category==={{ $i }}" aria-controls="source-submenu-{{ $i }}"><span class="category-text">{{ $local($item['href'])==='/services' ? 'خدمات الکترونیکی' : $item['title'] }}</span>@include('icons.arrow')</button></li>@endforeach</ul>
                        <div class="submenus">@foreach($categories as $i=>$item)<div class="submenu {{ $i===0 ? 'active' : '' }}" :class="{'active':category==={{ $i }}}" x-show="category==={{ $i }}" id="source-submenu-{{ $i }}">
                            <div class="source-mega-heading"><h2>{{ $item['title'] }}</h2></div>
                            @include('partials.mega-branch',['branch'=>$item])
                        </div>@endforeach</div>
                        <footer class="source-mega-footer">@foreach($categories as $i=>$item)<a href="{{ $item['href'] }}" x-show="category==={{ $i }}">مشاهدهٔ همهٔ {{ $item['title'] }} @include('icons.arrow')</a>@endforeach</footer>
                    </div>
                </div>
                @endif
                @if($countySections['nav_polls']??true)<div class="menu-item"><div class="rast">@include('icons.poll')<a href="{{ $ui->href('/polls',$county) }}">نظرسنجی</a></div></div>@endif
            </div>
            <div class="logo-wrapper"><a href="{{ $ui->href('/',$county) }}" aria-label="صفحه اصلی {{ $portalName }}"><img src="{{ $logo }}" width="76" height="82" alt="{{ $portalName }}"></a></div>
            <div class="menu-left">
                @foreach(['/about'=>'about','/managers'=>'people'] as $href=>$icon)
                    @php($item=$rootLink($href))
                    @if($item)<div class="menu-item source-direct-menu" x-data="{open:false,timer:null}" :class="{'is-open':open}" @mouseenter="clearTimeout(timer);open=true;mega=false" @mouseleave="timer=setTimeout(()=>open=false,240)" @focusin="open=true;mega=false" @focusout="if(!$el.contains($event.relatedTarget))open=false" @click.outside="open=false" @keydown.escape.prevent.stop="open=false">
                        <div class="rast">@include('icons.'.$icon)<a href="{{ $item['href'] }}">{{ $item['title'] }}</a><button class="source-direct-toggle" type="button" aria-label="زیرمنوی {{ $item['title'] }}" :aria-expanded="open" @click="open=true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button></div>
                        @if(!empty($item['children']))
                            <div class="source-direct-panel" x-show="open" x-cloak>
                                <aside class="source-mega-feature"><span class="source-mega-feature-icon">@include('icons.'.$icon)</span><h2>{{ $item['title'] }}</h2><a href="{{ $item['href'] }}">مشاهدهٔ همه @include('icons.arrow')</a></aside>
                                <div class="source-direct-grid">@foreach($item['children'] as $child)<div><a @class(['source-direct-title','has-custom-page-image'=>($child['link_style']??'')==='image']) href="{{ $child['href'] }}">@include('partials.custom-link-image',['link'=>$child])<span>{{ $child['title'] }}</span>@include('icons.arrow')</a>@foreach($child['children'] ?? [] as $link)<a class="source-direct-child" href="{{ $link['href'] }}">{{ $link['title'] }}</a>@endforeach</div>@endforeach</div>
                            </div>
                        @endif
                    </div>@endif
                @endforeach
                @if($countySections['nav_agreement']??true)<div class="menu-item"><div class="rast">@include('icons.agreement')<a href="{{ $ui->href('/service-agreement',$county) }}">توافق خدمات</a></div></div>@endif
                @if($countySections['nav_assistant']??true)<div class="menu-item ai-menu-item"><div class="rast"><span class="ai-badge">@include('icons.ai') AI</span><a href="{{ $ui->href('/assistant',$county) }}" aria-haspopup="dialog" aria-controls="assistant-development-notice">هوش مصنوعی سازمانی</a></div></div>@endif
            </div>
        </nav>
        <div class="mm-backdrop" id="mmBackdrop" :class="{'active':mobile}" x-show="mobile" x-cloak @click="dismiss()"></div>
        <div class="mobile-menu-overlay active" id="mobileMenu" role="dialog" aria-modal="true" aria-label="فهرست سایت" x-ref="drawer" x-show="mobile" x-cloak @keydown.tab="trap($event)">
            <div class="mm-header"><img src="{{ $logo }}" class="mm-logo" alt="{{ $portalName }}"><button type="button" x-ref="closeMenu" id="closeMobileMenu" class="mm-close" aria-label="بستن منو" @click="dismiss()">×</button></div>
            @if(($countySections['nav_duties']??true)&&!collect($navigation)->contains(fn($entry)=>$local($entry['href'])==='/duties'))<a class="mm-link" href="{{ $ui->href('/duties',$county) }}">شرح وظایف واحدها</a>@endif
            @include('partials.mobile-menu',['entries'=>$navigation])
            @if(($countySections['nav_assistant']??true)&&!collect($navigation)->contains(fn($entry)=>$local($entry['href'])==='/assistant'))<a class="mm-link" href="{{ $ui->href('/assistant',$county) }}" aria-haspopup="dialog" aria-controls="assistant-development-notice">هوش مصنوعی سازمانی</a>@endif
            <div class="mm-utility">@foreach($utility as [$label,$href])<a href="{{ $ui->href($href,$county) }}">{{ $label }}</a>@endforeach</div><div class="mm-footer"><a href="{{ $ui->href('/install',$county) }}">نصب اپلیکیشن استان یار</a><a href="/sitemap">نقشه سایت</a></div>
        </div>
    </div><div class="header-paen"><div class="marquee"><div class="marquee-content"><span>{{ $settings['site.slogan_text'] ?? 'اقتصاد مقاومتی در سایه وحدت ملی و امنیت ملی' }}</span></div></div></div></div>
    <dialog id="assistant-development-notice" class="portal-assistant-notice" x-ref="assistantNotice" aria-labelledby="assistant-development-title" @keydown.escape.stop>
        <div class="portal-assistant-notice-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="7" width="16" height="13" rx="4"/><path d="M12 3v4M2 12v4m20-4v4M9 16h6"/><circle cx="9" cy="12" r=".8"/><circle cx="15" cy="12" r=".8"/></svg>
        </div>
        <h2 id="assistant-development-title">دستیار هوش مصنوعی سازمانی در حال توسعه است</h2>
        <div class="portal-assistant-notice-action"><button type="button" @click="$refs.assistantNotice.close()" autofocus>متوجه شدم</button></div>
    </dialog>
</header>
