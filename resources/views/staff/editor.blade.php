@extends('staff.layout')
@section('staff-body')
<form class="staff-form" method="post" enctype="multipart/form-data" action="{{ $portal->adminPath() }}/items{{ $item->exists?'/'.$item->id:'' }}" x-data="staffEditor" data-staff-portal-editor data-image-endpoint="staff-portals/{{ $portal->slug }}/assets">@csrf
    <input type="hidden" name="kind" value="{{ $item->kind }}"><section class="staff-card"><h2>{{ \App\Services\StaffPortals::KINDS[$item->kind] }}</h2><label for="staff-item-title">عنوان<input id="staff-item-title" name="title" value="{{ old('title',$item->title) }}" required maxlength="255"></label>
    @if(in_array($item->kind,['link','video']))<label for="staff-item-url">نشانی {{ $item->kind==='video'?'ویدئو':'پیوند' }}<input id="staff-item-url" name="url" type="url" value="{{ old('url',$item->url) }}" required maxlength="2000" dir="ltr" placeholder="https://"></label>@endif
    @if($item->kind==='slide')<label for="staff-item-url">پیوند اسلاید (اختیاری)<input id="staff-item-url" name="url" type="url" value="{{ old('url',$item->url) }}" maxlength="2000" dir="ltr" placeholder="https://"></label><p class="staff-help">نشانی کامل خبر یا صفحه مقصد را وارد کنید. بدون پیوند، فقط تصویر نمایش داده می‌شود.</p>@endif
    @if(in_array($item->kind,['news','announcement','video']))<label for="staff-item-summary">خلاصه<textarea id="staff-item-summary" name="summary" rows="3" maxlength="3000">{{ old('summary',$item->summary) }}</textarea></label>@endif
    @if(in_array($item->kind,['news','announcement']))<label for="staff-item-body">متن کامل</label><textarea id="staff-item-body" name="body" data-rich-text="true" data-field="body" rows="14" required>{{ old('body',$item->body) }}</textarea>@endif
    </section>
    <section class="staff-card"><h2>{{ $item->kind==='gallery'?'تصاویر':'تصویر شاخص' }}</h2>
        @if($item->kind==='slide')<p class="staff-help">تصویر اسلاید الزامی است. اسلایدهای منتشرشده جایگزین تصاویر پیش‌فرض می‌شوند.</p>@endif
        @if($item->kind==='gallery' && !$item->exists)<label for="staff-item-images">بارگذاری چند تصویر<input id="staff-item-images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp"></label><p class="staff-help">حداکثر ۲۰ تصویر؛ هر تصویر تا ۸ مگابایت.</p>@else<label for="staff-item-image">بارگذاری تصویر<input id="staff-item-image" name="image_file" type="file" accept="image/jpeg,image/png,image/webp"></label>@endif
        <label for="staff-item-asset">انتخاب از تصاویر من<select id="staff-item-asset" name="asset_id"><option value="">انتخاب تصویر</option>@foreach($assets as $image)<option value="{{ $image->id }}" @selected((string)old('asset_id',$item->asset_id)===(string)$image->id)>{{ $image->original_name }}</option>@endforeach</select></label>
        @if($item->asset)<img class="staff-image-preview" src="{{ $item->asset->url }}" alt="{{ $item->title }}">@endif
    </section>
    <section class="staff-card"><h2>انتشار</h2><div class="staff-form-grid"><label for="staff-item-status">وضعیت<select id="staff-item-status" name="status"><option value="draft" @selected(old('status',$item->status)==='draft')>پیش‌نویس</option><option value="published" @selected(old('status',$item->status)==='published')>منتشرشده</option></select></label><label for="staff-item-priority">ترتیب نمایش<input id="staff-item-priority" type="number" name="priority" value="{{ old('priority',$item->priority??0) }}" min="0" max="100000" required></label></div>
        <label for="staff-item-date">زمان انتشار<input id="staff-item-date" type="datetime-local" name="published_at" value="{{ old('published_at',$item->published_at?->format('Y-m-d\TH:i')) }}" dir="ltr"></label><p class="staff-help">با خالی‌گذاشتن زمان، محتوا پس از انتخاب «منتشرشده» نمایش داده می‌شود.</p>
    </section><div class="staff-form-actions"><button class="staff-primary" type="submit">ذخیره محتوا</button><a class="staff-secondary" href="{{ $portal->adminPath() }}?tab={{ $item->kind }}">بازگشت</a></div>
</form>
@endsection
