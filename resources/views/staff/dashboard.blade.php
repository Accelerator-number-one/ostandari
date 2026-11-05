@extends('staff.layout')
@section('staff-body')
@if($tab==='profile')
<form class="staff-form" method="post" enctype="multipart/form-data" action="{{ $portal->adminPath() }}/profile" x-data="staffEditor" data-staff-portal-editor data-image-endpoint="staff-portals/{{ $portal->slug }}/assets">@csrf
    <section class="staff-card"><h2>معرفی صفحه</h2><label for="staff-title">عنوان صفحه<input id="staff-title" name="title" value="{{ old('title',$portal->title) }}" required maxlength="255"></label>
        <label for="staff-summary">معرفی کوتاه<textarea id="staff-summary" name="summary" rows="3" maxlength="3000">{{ old('summary',$portal->summary) }}</textarea></label>
        <label for="staff-body">معرفی و شرح فعالیت‌ها</label><textarea id="staff-body" name="body" rows="12" data-rich-text="true" data-field="body">{{ old('body',$portal->body) }}</textarea>
    </section>
    <section class="staff-card"><h2>تصاویر صفحه</h2><div class="staff-form-grid">
        @foreach(['portrait'=>'تصویر مدیر','banner'=>'بنر صفحه'] as $field=>$label)<div><label for="staff-{{ $field }}">{{ $label }}</label>@if($portal->$field)<img class="staff-image-preview" src="{{ $portal->$field->url }}" alt="{{ $label }}">@endif
            <input id="staff-{{ $field }}" type="file" name="{{ $field }}_file" accept="image/jpeg,image/png,image/webp">
            <label for="staff-{{ $field }}-id">انتخاب از تصاویر من<select id="staff-{{ $field }}-id" name="{{ $field }}_id"><option value="">بدون تصویر اختصاصی</option>@foreach($portal->assets()->latest('id')->get() as $image)<option value="{{ $image->id }}" @selected((string)old($field.'_id',$portal->{$field.'_id'})===(string)$image->id)>{{ $image->original_name }}</option>@endforeach</select></label>
        </div>@endforeach
    </div></section>
    <section class="staff-card"><h2>اطلاعات تماس</h2><div class="staff-form-grid"><label for="staff-phone">تلفن<input id="staff-phone" name="phone" value="{{ old('phone',$portal->phone) }}" maxlength="60" dir="ltr"></label><label for="staff-email">ایمیل<input id="staff-email" name="email" type="email" value="{{ old('email',$portal->email) }}" maxlength="255" dir="ltr"></label></div><label for="staff-address">نشانی<textarea id="staff-address" name="address" rows="2" maxlength="1000">{{ old('address',$portal->address) }}</textarea></label></section>
    <div class="staff-form-actions"><button class="staff-primary" type="submit">ذخیره مشخصات صفحه</button><a class="staff-secondary" href="{{ $portal->publicPath() }}">مشاهده صفحه</a></div>
</form>
@elseif($tab==='pages')
@include('staff.pages-list')
@elseif($tab==='assets')
<section class="staff-card"><h2>افزودن تصویر</h2><form class="staff-upload-form" action="{{ $portal->adminPath() }}/assets" method="post" enctype="multipart/form-data">@csrf<label for="staff-upload">تصویر<input id="staff-upload" type="file" name="file" required accept="image/jpeg,image/png,image/webp"></label><button class="staff-primary" type="submit">بارگذاری</button></form></section>
<section class="staff-card"><h2>تصاویر من</h2><div class="staff-asset-grid">@foreach($assets as $image)<figure><img src="{{ $image->url }}" alt="{{ $image->original_name }}" loading="lazy"><figcaption>{{ $image->original_name }}</figcaption></figure>@endforeach</div>{{ $assets->links() }}</section>
@elseif($tab==='account')
<form class="staff-form staff-card" method="post" action="{{ $portal->adminPath() }}/password">@csrf<h2>تغییر رمز عبور</h2><label for="staff-old-pass">رمز عبور فعلی<input id="staff-old-pass" type="password" name="current_password" required autocomplete="current-password" dir="ltr"></label><label for="staff-new-pass">رمز عبور جدید<input id="staff-new-pass" type="password" name="password" required minlength="12" autocomplete="new-password" dir="ltr"></label><label for="staff-confirm-pass">تکرار رمز عبور جدید<input id="staff-confirm-pass" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" dir="ltr"></label><p class="staff-help">حداقل ۱۲ نویسه، شامل حروف کوچک و بزرگ انگلیسی و عدد.</p><button class="staff-primary" type="submit">ذخیره رمز عبور</button></form>
@else
<section class="staff-card"><div class="staff-list-head"><h2>{{ \App\Services\StaffPortals::KINDS[$tab] }}</h2><a class="staff-primary" href="{{ $portal->adminPath() }}/items/new?kind={{ $tab }}">افزودن</a></div>
    <div class="staff-list-tools"><form method="get"><input type="hidden" name="tab" value="{{ $tab }}">@if(request()->boolean('trash'))<input type="hidden" name="trash" value="1">@endif<input type="search" name="q" value="{{ request('q') }}" aria-label="جستجوی عنوان" placeholder="جستجوی عنوان" maxlength="150"><button class="staff-secondary" type="submit">جستجو</button></form><a href="{{ $portal->adminPath() }}?tab={{ $tab }}{{ request()->boolean('trash')?'':'&trash=1' }}">{{ request()->boolean('trash')?'بازگشت به فهرست':'زباله‌دان' }}</a></div>
    <div class="staff-table-wrap"><table class="staff-table"><thead><tr><th>عنوان</th><th>وضعیت</th><th>تاریخ انتشار</th><th>عملیات</th></tr></thead><tbody>
    @forelse($items as $row)<tr><td><span class="staff-table-title">{{ $row->title }}</span></td><td><span class="staff-status {{ $row->status }}">{{ $row->status==='published'?'منتشرشده':'پیش‌نویس' }}</span></td><td>{{ $row->published_at ? $ui->date($row->published_at->toIso8601String()) : '—' }}</td><td><div class="staff-row-actions">@if(request()->boolean('trash'))<form method="post" action="{{ $portal->adminPath() }}/items/{{ $row->id }}/restore">@csrf<button type="submit">بازیابی</button></form>@else<a href="{{ $portal->adminPath() }}/items/{{ $row->id }}/edit">ویرایش</a>@if($row->status==='published' && in_array($row->kind,['news','announcement']))<a href="{{ $portal->publicPath() }}/content/{{ $row->id }}" target="_blank" rel="noopener">نمایش</a>@endif<form method="post" action="{{ $portal->adminPath() }}/items/{{ $row->id }}/delete">@csrf<button class="staff-danger" type="submit">حذف</button></form>@endif</div></td></tr>@empty<tr><td colspan="4">موردی ثبت نشده است.</td></tr>@endforelse
    </tbody></table></div>{{ $items->links() }}
</section>
@endif
@endsection
