@extends('staff.layout')
@section('staff-body')
<form class="staff-form" method="post" action="{{ $portal->adminPath() }}/pages{{ $staffPage->exists?'/'.$staffPage->id:'' }}" x-data="staffEditor" data-staff-portal-editor data-image-endpoint="staff-portals/{{ $portal->slug }}/assets">@csrf
    <section class="staff-card"><h2>محتوای صفحه</h2>
        <label for="staff-page-title">عنوان صفحه<input id="staff-page-title" name="title" value="{{ old('title',$staffPage->title) }}" required maxlength="255"></label>
        <label for="staff-page-path">مسیر صفحه<input id="staff-page-path" name="path" value="{{ old('path',$staffPage->path) }}" required maxlength="100" dir="ltr" placeholder="contact"></label>
        <p class="staff-help">مسیر پس از <bdi>{{ $portal->publicPath() }}/pages/</bdi> قرار می‌گیرد. حروف، عدد، خط تیره و زیرخط مجاز است.</p>
        <label for="staff-page-body">متن صفحه</label><textarea id="staff-page-body" name="body" rows="14" data-rich-text="true" data-field="body" required>{{ old('body',$staffPage->body) }}</textarea>
    </section>
    <section class="staff-card"><h2>نمایش در منو</h2>
        <input type="hidden" name="show_in_navigation" value="0"><label class="staff-checkbox" for="staff-page-menu"><input id="staff-page-menu" name="show_in_navigation" type="checkbox" value="1" @checked(old('show_in_navigation',$staffPage->show_in_navigation))><span>پیوند این صفحه در منوی صفحه شخصی نمایش داده شود</span></label>
        <div class="staff-form-grid"><label for="staff-page-nav-title">عنوان پیوند (اختیاری)<input id="staff-page-nav-title" name="nav_title" value="{{ old('nav_title',$staffPage->nav_title) }}" maxlength="120" placeholder="عنوان صفحه"></label><label for="staff-page-priority">ترتیب نمایش<input id="staff-page-priority" name="priority" type="number" value="{{ old('priority',$staffPage->priority??0) }}" min="0" max="100000" required></label></div>
        <p class="staff-help">فقط صفحه‌های منتشرشده وارد منو می‌شوند. «صفحه اصلی» همیشه به صفحه شخصی خودتان برمی‌گردد.</p>
    </section>
    <section class="staff-card"><h2>انتشار</h2><div class="staff-form-grid"><label for="staff-page-status">وضعیت<select id="staff-page-status" name="status"><option value="draft" @selected(old('status',$staffPage->status)==='draft')>پیش‌نویس</option><option value="published" @selected(old('status',$staffPage->status)==='published')>منتشرشده</option></select></label><label for="staff-page-date">زمان انتشار (اختیاری)<input id="staff-page-date" name="published_at" type="datetime-local" value="{{ old('published_at',$staffPage->published_at?->format('Y-m-d\TH:i')) }}" dir="ltr"></label></div></section>
    <div class="staff-form-actions"><button class="staff-primary" type="submit">ذخیره صفحه</button><a class="staff-secondary" href="{{ $portal->adminPath() }}?tab=pages">بازگشت</a>@if($staffPage->exists && $staffPage->status==='published')<a class="staff-secondary" href="{{ $staffPage->publicPath() }}" target="_blank" rel="noopener">مشاهده صفحه</a>@endif</div>
</form>
@endsection
