@once
    @push('styles')<link rel="stylesheet" href="/styles/member-cards.css">@endpush
@endonce
@php
    $memberProfile=($view??null)==='members' && request()->filled('person');
    $staffPersonalLinks=\App\Services\StaffPortals::links($items);
@endphp
@if(($view??null)==='members' && !$memberProfile && isset($directoryGroup))
@once
    @push('styles')<link rel="stylesheet" href="/styles/manager-directory.css">@endpush
@endonce
<nav class="portal-member-directory-tabs" aria-label="فهرست مدیران و مسئولان استان">
    @foreach(\App\Services\ManagerPages::GROUPS as $groupKey=>$definition)
        <a href="{{ \App\Services\ManagerPages::path((string)$groupKey) }}" @if((string)$groupKey===$directoryGroup)aria-current="page"@endif>{{ $definition['title'] }}</a>
    @endforeach
</nav>
@endif
@if(($view??null)==='members' && !$memberProfile && !empty($directoryPage))
<section class="portal-card portal-manager-download" aria-label="فهرست مدیران روابط عمومی">
    <div class="portal-article-body portal-rich-text">{!! \App\Services\Html::articleBody($directoryPage->body ?? '') !!}</div>
</section>
@endif
<div class="manager-grid portal-member-grid {{ $memberProfile?'is-profile':'' }}">
@foreach($items as $person)
@php
    $memberOrganization=$person['organization']['name']??(($person['organization_id']??null)===($home['organization']['id']??0)?($home['organization']['name']??''):'');
@endphp
<article class="portal-member-card {{ $memberProfile?'is-profile':'' }}">
    <header class="portal-member-heading">
        <div class="portal-member-portrait" x-data="{failed:false}">
            @if($image=$ui->image($person))
                <img src="{{ $image }}" alt="{{ empty($person['media_id']) && !empty($person['image_url']) ? 'تصویر موقت' : $person['name'] }}" width="88" height="88" loading="lazy" x-show="!failed" x-init="failed=$el.complete && !!$el.currentSrc && $el.naturalWidth===0" x-on:load="failed=false" x-on:error="failed=true">
                <span class="portal-member-avatar" x-show="failed" x-cloak aria-hidden="true">@include('icons.people')</span>
            @else
                <span class="portal-member-avatar" aria-hidden="true">@include('icons.people')</span>
            @endif
        </div>
        <div class="portal-member-identity"><h3 title="{{ $person['name'] }}">{{ $person['name'] }}</h3><p class="portal-member-position" title="{{ $person['position'] ?? '' }}">{{ $person['position'] ?? '' }}</p></div>
    </header>
    @if(($person['kind']??'')==='governor' && !empty($person['term_period']))<p class="portal-governor-term"><span>دوره تصدی</span><bdi>{{ $person['term_period'] }}</bdi></p>@endif
    @if($memberOrganization && $memberOrganization!==($person['position']??''))<p class="portal-member-organization" title="{{ $memberOrganization }}">{{ $memberOrganization }}</p>@endif
    @if($memberProfile)
        @if(!empty($person['bio']))<p class="portal-member-bio">{{ $ui->text($person['bio']) }}</p>@endif
        @if(!empty($person['body']))<div class="portal-article-body">{!! \App\Services\Html::clean($person['body']) !!}</div>@endif
    @endif
    <div class="portal-member-actions">
        @if(isset($staffPersonalLinks[$person['id']]))<a class="portal-member-personal" href="{{ $staffPersonalLinks[$person['id']] }}" aria-label="ورود به صفحه شخصی {{ $person['name'] }}">ورود به صفحه شخصی <span aria-hidden="true">←</span></a>@endif
        <a class="portal-member-profile" href="{{ $ui->href('/managers?person='.$person['id'],$county) }}" aria-label="مشاهدهٔ پروفایل {{ $person['name'] }}">مشاهده پروفایل <span aria-hidden="true">←</span></a>
    </div>
</article>
@endforeach
</div>
@if(($view??null)==='members' && !empty($meta) && $meta['last_page']>1)
<nav class="portal-pagination" aria-label="صفحه‌های مدیران">@for($number=max(1,$meta['current_page']-3);$number<=min($meta['last_page'],$meta['current_page']+3);$number++)<a href="{{ request()->fullUrlWithQuery(['page'=>$number]) }}" @if($number===$meta['current_page'])aria-current="page"@endif>{{ $number }}</a>@endfor</nav>
@endif
