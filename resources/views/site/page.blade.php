@extends('layouts.base')
@push('styles')<link rel="stylesheet" href="/styles/home-page.css">@endpush
@if($view==='counties')
    @push('styles')<link rel="stylesheet" href="/styles/county-directory.css">@endpush
@endif
@php
    $serviceRoots=[$ui->href('/services',$county),$ui->href('/service-agreement',$county)];
    $serviceNavigation=array_values(array_filter($navigation,fn($entry)=>in_array(parse_url($entry['href']??'',PHP_URL_PATH),$serviceRoots,true)));
    $servicePaths=[];
    $collectServicePaths=function($entries)use(&$collectServicePaths,&$servicePaths){
        foreach($entries as $entry){
            $host=parse_url($entry['href']??'',PHP_URL_HOST);
            if(!$host||$host===request()->getHost())$servicePaths[]=parse_url($entry['href']??'',PHP_URL_PATH);
            $collectServicePaths($entry['children']??[]);
        }
    };
    $collectServicePaths($serviceNavigation);
    $servicePage=in_array($path,['/services','/services/provincial','/service-agreement','/service-agreement/statement','/forms','/track','/support'],true)
        ||($resource??null)==='services'||$view==='service-agreement'||in_array(request()->getPathInfo(),$servicePaths,true);
@endphp
@section('body')
<div x-data="portalPage" data-design="{{ $page['scope'] }}" data-preview="false" data-section="site" data-path="{{ request()->getPathInfo() }}" class="min-h-screen w-full font-vazirmatn">
    <div class="container-asli source-portal" data-county-portal="{{ $county['slug'] ?? '' }}">
        @if($view==='staff-portal')
            @include('partials.staff-header')
        @else
            @include('partials.header')
        @endif
        <main id="main-content" class="source-main {{ $view==='home' ? 'source-home' : 'source-content' }} {{ $servicePage?'portal-service-page':'' }}" data-content-view="{{ $view }}">
            @if($view!=='home' && $view!=='staff-portal' && !$county)
                <nav class="source-breadcrumb" aria-label="مسیر صفحه"><a href="/">صفحه اصلی</a><span aria-hidden="true">/</span>@if(in_array($view,['article','duty'],true))<span aria-current="page">{{ ['news'=>'اخبار','announcement'=>'اطلاعیه‌ها','resolution'=>'مصوبات','duty'=>'شرح وظایف واحدها','event'=>'رویدادها'][$content['type'] ?? 'news'] ?? 'مطالب' }}</span>@elseif($view==='duties'||$servicePage||in_array($view,['service-agreement','custom-page'],true))<span aria-current="page">{{ $section['title'] ?? $title }}</span>@else<h1>{{ $title }}</h1>@endif</nav>
            @endif
            @if($servicePage)@include('site.services-layout')@else @include('site.'.$view) @endif
        </main>
    </div>
    @include('partials.footer')
</div>
@endsection
