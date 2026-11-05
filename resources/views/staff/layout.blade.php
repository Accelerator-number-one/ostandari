@extends('layouts.base')
@section('body')
<div class="staff-panel font-vazirmatn" dir="rtl" data-section="staff">
    <aside class="staff-panel-sidebar"><a class="staff-panel-brand" href="{{ $portal->adminPath() }}"><img src="/design/site/assets/image/logo.png" width="44" height="48" alt="استانداری مازندران"><span>{{ $portal->member->name }}</span></a>
        <nav aria-label="پنل صفحه شخصی">
            <a href="{{ $portal->adminPath() }}" @if($tab==='profile')aria-current="page"@endif>مشخصات صفحه</a>
            <a href="{{ $portal->adminPath() }}?tab=pages" @if($tab==='pages')aria-current="page"@endif>صفحات و منو</a>
            @foreach(\App\Services\StaffPortals::KINDS as $kind=>$label)<a href="{{ $portal->adminPath() }}?tab={{ $kind }}" @if($tab===$kind)aria-current="page"@endif>{{ $label }}</a>@endforeach
            <a href="{{ $portal->adminPath() }}?tab=assets" @if($tab==='assets')aria-current="page"@endif>تصاویر من</a>
            <a href="{{ $portal->adminPath() }}?tab=account" @if($tab==='account')aria-current="page"@endif>تغییر رمز عبور</a>
        </nav>
        <a class="staff-panel-site" href="{{ $portal->publicPath() }}" target="_blank" rel="noopener">مشاهده صفحه شخصی ↗</a>
        <form method="post" action="{{ $portal->adminPath() }}/logout">@csrf<button class="staff-logout" type="submit">خروج</button></form>
    </aside>
    <main class="staff-panel-main" id="main-content"><header class="staff-panel-top"><div><span>{{ $portal->title }}</span><h1>{{ $title }}</h1></div><a class="staff-secondary" href="{{ $portal->publicPath() }}">نمایش صفحه</a></header>
        @if(session('status'))<p class="staff-notice" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="staff-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('staff-body')
    </main>
</div>
@endsection
