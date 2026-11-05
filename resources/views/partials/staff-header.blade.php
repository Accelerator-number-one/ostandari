@php
    $logo=$ui->image($settings['site.logo'] ?? null) ?? '/design/site/assets/image/logo.png';
    $staffNav=collect([['title'=>'صفحه اصلی','href'=>$portal->publicPath(),'active'=>!$staffPage && !$staffItem]])
        ->concat($staffNavigation->map(fn($entry)=>['title'=>$entry->nav_title ?: $entry->title,'href'=>$entry->publicPath(),'active'=>$staffPage?->id===$entry->id]));
    $navSplit=(int)ceil($staffNav->count()/2);
@endphp
<header class="header-container staff-site-header" data-portal-ui="staff-header-20261030" data-staff-header="{{ $portal->slug }}" data-alpine-header="true" x-data="portalHeader" @keydown.escape.window="dismiss()" @click.outside="language=false">
    <a class="source-skip" href="#main-content">رفتن به محتوای صفحه</a>
    <div class="nav-bala">
        <nav class="nav-bala-right" aria-label="پیوندهای بالای سایت">@foreach([['تماس با ما','/contact'],['دسترسی سریع','/quick-access'],['سامانه شفافیت','/transparency'],['قوانین و مقررات','/regulations']] as [$label,$href])<a href="{{ $href }}">{{ $label }}</a>@endforeach</nav>
        <div class="nav-bala-left">
            <a class="header-install-app" href="/install">@include('icons.app')<span>نصب اپلیکیشن استان یار</span></a>
            <div class="iran-container"><div class="iran-img"><button type="button" id="flagSwitcher" aria-label="انتخاب زبان" aria-controls="flagDropdown" :aria-expanded="language" @click="language=!language">@include('icons.iran')</button><div class="flag-dropdown" :class="{'active':language}" x-show="language" x-cloak id="flagDropdown">@include('partials.language-options')</div></div><a class="search" href="/search" aria-label="جستجو در سایت">@include('icons.search')</a></div>
            <div class="sabtnam-container"><div class="sabtnan"><a href="/register">ثبت‌نام شهروندان</a></div><div class="vorod"><a href="{{ auth()->check() && !auth()->user()->isStaff() ? '/citizen' : '/login' }}">{{ auth()->check() && !auth()->user()->isStaff() ? 'پنل شهروندی' : 'ورود شهروندان' }}</a></div></div>
        </div>
    </div>
    <div class="imageeee" data-portal-banner="true"><img class="icx-responsive-img" src="{{ $ui->image($settings['site.banner'] ?? null) ?? '/design/site/assets/image/baner.png' }}" width="1380" height="180" alt="استانداری مازندران" fetchpriority="high"><div class="shoar-sal"><img src="{{ $ui->image($settings['site.slogan'] ?? null) ?? '/design/county-banners/annual-slogan.png' }}" alt="شعار سال"></div></div>
    <div class="top-header">
        <div class="staff-site-navbar">
            <button type="button" class="header-hamburger" id="hamburgerBtn" aria-label="باز کردن منوی صفحه شخصی" :aria-expanded="mobile" aria-controls="mobileMenu" @click="mobile=true"><span></span><span></span><span></span></button>
            <nav class="staff-site-navigation" aria-label="منوی صفحه شخصی">
                <div class="staff-nav-group">@foreach($staffNav->take($navSplit) as $entry)<a href="{{ $entry['href'] }}" @if($entry['active'])aria-current="page"@endif>{{ $entry['title'] }}</a>@endforeach</div>
                <span class="staff-nav-logo-space" aria-hidden="true"></span>
                <div class="staff-nav-group">@foreach($staffNav->skip($navSplit) as $entry)<a href="{{ $entry['href'] }}" @if($entry['active'])aria-current="page"@endif>{{ $entry['title'] }}</a>@endforeach</div>
            </nav>
            <a class="staff-site-logo" href="{{ $portal->publicPath() }}" aria-label="صفحه اصلی {{ $portal->title }}"><img src="{{ $logo }}" width="76" height="82" alt="استانداری مازندران"></a>
        </div>
        <div class="mm-backdrop" :class="{'active':mobile}" x-show="mobile" x-cloak @click="dismiss()"></div>
        <div class="mobile-menu-overlay active" id="mobileMenu" role="dialog" aria-modal="true" aria-label="منوی صفحه شخصی" x-ref="drawer" x-show="mobile" x-cloak @keydown.tab="trap($event)">
            <div class="mm-header"><img src="{{ $logo }}" class="mm-logo" alt="استانداری مازندران"><button type="button" x-ref="closeMenu" class="mm-close" aria-label="بستن منو" @click="dismiss()">×</button></div>
            @foreach($staffNav as $entry)<a class="mm-link" href="{{ $entry['href'] }}" @if($entry['active'])aria-current="page"@endif>{{ $entry['title'] }}</a>@endforeach
        </div>
        <div class="header-paen"><div class="marquee"><div class="marquee-content"><span>{{ $settings['site.slogan_text'] ?? 'اقتصاد مقاومتی در سایه وحدت ملی و امنیت ملی' }}</span></div></div></div>
    </div>
</header>
