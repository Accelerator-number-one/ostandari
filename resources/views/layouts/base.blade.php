@php
    $browserCounty=is_array($county??null)?$county:null;
    $browserSiteTitle=$browserCounty
        ? ($home['county_portal']['title']??'فرمانداری شهرستان '.$browserCounty['name'])
        : 'درگاه الکترونیکی استانداری مازندران';
    $browserPageTitle=trim(strip_tags((string)($title??'')));
    $browserFavicon=app(\App\Services\PortalView::class)->image($settings['site.logo']??null)??'/design/site/assets/image/logo.png';
    if($browserCounty)$browserPageTitle=app(\App\Services\PortalView::class)->countyTitle($browserPageTitle,$browserCounty);
    $browserHome=($view??null)==='home'||($browserCounty&&($path??null)==='/');
    $browserDocumentTitle=$browserHome||in_array($browserPageTitle,['','صفحه اصلی','پرتال استانداری مازندران',$browserSiteTitle],true)
        ? $browserSiteTitle
        : $browserPageTitle.' | '.$browserSiteTitle;
@endphp
<!doctype html>
<html lang="{{ $page['lang'] ?? 'fa' }}" dir="{{ ($page['lang'] ?? 'fa') === 'en' ? 'ltr' : 'rtl' }}" data-citizen-demo="{{ config('website.citizen_demo') ? 'true' : 'false' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $browserDocumentTitle }}</title>
    <link rel="icon" href="{{ $browserFavicon }}">
    @if(($page['controller'] ?? null)==='site')
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/pwa/icon-192.png">
        <meta name="theme-color" content="#075b80">
        <script src="/pwa/install.js" defer></script>
    @endif
    <meta name="description" content="{{ $description ?? 'درگاه الکترونیکی استانداری مازندران و خدمات مردمی' }}">
    <link rel="preload" as="font" type="font/woff2" href="/design/site/assets/fonts/Vazirmatn-VariableFont_wght.woff2" crossorigin="anonymous">
    @isset($page['css'])<link rel="stylesheet" href="{{ $page['css'] }}">@endisset
    @vite(['resources/css/app.css','resources/js/app.ts'])
    @stack('styles')
</head>
<body>
@yield('body')
@isset($page)<script id="portal-page" type="application/json">{!! \Illuminate\Support\Js::encode($page) !!}</script>@endisset
@stack('scripts')
</body>
</html>
