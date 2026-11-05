@extends('layouts.base')
@section('body')
@php($demo=$mode!=='staff' && config('website.citizen_demo'))
<main class="portal-auth-page portal-auth-refresh font-vazirmatn" dir="rtl" x-data="authForm(@js($mode),@js($county['slug'] ?? ''),@js(\App\Services\RegistrationTerms::VERSION),@js(\App\Services\RegistrationTerms::documentHash()))"><section class="portal-auth-card {{ $mode==='register'?'portal-auth-card--registration':'' }}" aria-labelledby="authentication-title">
<a class="portal-auth-brand" href="{{ $county?'/'.$county['slug']:'/' }}"><img src="/design/site/assets/image/logo.png" alt="استانداری مازندران" width="70" height="76"><span>{{ $county?'فرمانداری شهرستان '.$county['name']:'پرتال استانداری مازندران' }}</span></a>@if($mode==='staff')<span class="portal-auth-kicker">{{ $county?'روابط عمومی فرمانداری':'ورود مدیران و روابط عمومی' }}</span>@endif<h1 id="authentication-title">{{ $title }}</h1>@if($mode!=='staff')<p>{{ $demo?'برای آزمایش پنل شهروندی، اطلاعات دلخواه وارد کنید.':'اطلاعات خود را وارد کنید؛ ورود با کد تأیید شماره همراه انجام می‌شود.' }}</p>@endif
@if(request('error')==='auth_required')<p class="portal-auth-error" role="alert">برای مشاهدهٔ پنل مدیریت ابتدا وارد حساب مدیریتی شوید.</p>@endif
<p class="portal-auth-error" role="alert" x-show="error" x-text="error" x-cloak></p>
@if($demo)<p class="portal-auth-notice">محیط آزمایشی: ورود با اطلاعات دلخواه انجام می‌شود. حساب و درخواست‌ها فقط در همین مرورگر ذخیره می‌شوند.</p>@endif
<form class="portal-auth-form" @submit.prevent="submit()" @if($demo)novalidate @endif><fieldset :disabled="busy" class="{{ $mode==='register'?'portal-registration-layout':'' }}">
    @if($mode==='staff')<label>نام کاربری<input name="login" x-model="login" required autocomplete="username" maxlength="255" autofocus></label><label>رمز عبور<input type="password" name="password" x-model="password" required autocomplete="current-password" maxlength="200"></label>
    @else
    <div class="portal-registration-fields" x-show="!challenge">
        @if($mode==='register')<label>نام و نام خانوادگی<input name="name" x-model="name" @if(!$demo)required minlength="3"@endif maxlength="120" autocomplete="name"></label>@endif
        <label>شمارهٔ همراه<input name="mobile" x-model="mobile" dir="ltr" inputmode="tel" autocomplete="tel-national" @if(!$demo)required @endif maxlength="40"></label>
        @if($mode==='register')<label>کد ملی<input name="national_code" x-model="national_code" dir="ltr" inputmode="numeric" @if(!$demo)required @endif maxlength="40"></label>@endif
    </div><div class="portal-auth-challenge" x-show="challenge" x-cloak><p>کد تأیید ارسال‌شده به <bdi x-text="mobile"></bdi> را وارد کنید.</p><label>کد تأیید<input x-model="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" :required="!!challenge" maxlength="6"></label><p x-show="devCode" class="portal-auth-notice">کد محیط توسعه: <bdi x-text="devCode"></bdi></p></div>
    @endif
    @if($mode==='register')@include('auth.terms')<label class="portal-registration-consent"><input type="checkbox" name="terms_accepted" x-model="accepted" required><span>متن کامل قوانین و مقررات را مطالعه کردم و می‌پذیرم.</span></label>@endif
    <button type="submit" :disabled="busy||{{ $mode==='register'?'!accepted':'false' }}" x-text="busy?'در حال بررسی…':(challenge?'تأیید و ورود':@js($mode==='staff'?'ورود به مدیریت':($mode==='register'?'ثبت‌نام و ورود':'ورود شهروندان')))">ورود</button>
</fieldset></form><nav class="portal-auth-links">@if($mode==='login')<a href="/register">حساب ندارید؟ ثبت‌نام شهروندان</a>@elseif($mode==='register')<a href="/login">قبلاً ثبت‌نام کرده‌اید؟ ورود شهروندان</a>@endif<a href="{{ $county?'/'.$county['slug']:'/' }}">بازگشت به سایت</a></nav></section></main>
@endsection
