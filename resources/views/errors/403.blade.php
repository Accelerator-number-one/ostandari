@extends('layouts.base')
@section('body')<main class="mx-auto max-w-2xl p-10 font-vazirmatn"><h1 class="text-2xl font-bold">دسترسی مجاز نیست</h1><p class="my-6">{{ $exception->getMessage() ?: 'این حساب اجازهٔ مشاهده یا تغییر این بخش را ندارد.' }}</p><a href="/panel/admin/login">ورود مدیریت</a> · <a href="/">صفحه اصلی</a></main>@endsection
