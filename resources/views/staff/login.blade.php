@extends('layouts.base')
@section('body')
<main class="portal-auth-page portal-auth-refresh staff-login font-vazirmatn" dir="rtl"><section class="portal-auth-card" aria-labelledby="staff-login-title">
    <a class="portal-auth-brand" href="{{ $portal->publicPath() }}"><img src="/design/site/assets/image/logo.png" width="70" height="76" alt="استانداری مازندران"><span>{{ $portal->title }}</span></a>
    <h1 id="staff-login-title">ورود {{ $portal->member->name }}</h1>
    @if(request('error')==='auth_required')<p class="portal-auth-error" role="alert">برای مشاهدهٔ پنل وارد شوید.</p>@endif
    @if(session('status'))<p class="staff-notice">{{ session('status') }}</p>@endif
    @if($errors->any())<ul class="portal-auth-error" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form class="portal-auth-form" method="post" action="{{ $portal->adminPath() }}/login">@csrf
        <fieldset><label for="staff-login">نام کاربری<input id="staff-login" name="login" value="{{ old('login') }}" required maxlength="255" autocomplete="username" dir="ltr"></label>
        <label for="staff-password">رمز عبور<input id="staff-password" name="password" type="password" required maxlength="200" autocomplete="current-password" dir="ltr"></label>
        <button type="submit">ورود به پنل</button></fieldset>
    </form>
    <nav class="portal-auth-links"><a href="{{ $portal->publicPath() }}">بازگشت به صفحه شخصی</a><a href="/">صفحه اصلی استان</a></nav>
</section></main>
@endsection
