@php
    $serviceDetail=($resource??null)==='services'&&(preg_match('~^/service/[^/]+$~',$path)||request()->filled('id'));
    $serviceTitle=$serviceDetail?($items[0]['name']??$title):match($path){
        '/services'=>'خدمات الکترونیکی',
        '/services/provincial'=>'خدمات استانداری مازندران',
        default=>$title,
    };
@endphp
@if(!in_array($view,['article','custom-page'],true))
<header class="portal-directory-heading">
    <span class="portal-directory-icon" aria-hidden="true">@include('icons.services-outline')</span>
    <h1>{{ $serviceTitle }}</h1>
    @if($serviceDetail)<a class="portal-directory-back" href="{{ $ui->href('/services',$county) }}">همهٔ خدمات <span aria-hidden="true">←</span></a>@endif
</header>
@endif
<div class="portal-service-layout {{ $serviceNavigation?'has-navigation':'' }}">
    <div class="portal-service-content">@include('site.'.$view)</div>
    @if($serviceNavigation)
    <aside class="portal-service-navigation" aria-label="فهرست خدمات">
        <h2>خدمات</h2>
        @foreach($serviceNavigation as $branch)
        <nav aria-label="{{ $branch['title'] }}">
            <a class="portal-service-navigation-title" href="{{ $branch['href'] }}" @if(parse_url($branch['href'],PHP_URL_PATH)===request()->getPathInfo())aria-current="page"@endif>{{ $branch['title'] }}</a>
            @include('partials.service-menu-links',['entries'=>$branch['children']??[]])
        </nav>
        @endforeach
    </aside>
    @endif
</div>
