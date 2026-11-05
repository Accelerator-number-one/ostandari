<div class="portal-county-settings portal-admin-content">
    <header class="portal-county-settings-heading"><p>تغییرات این صفحه فقط در پرتال فرمانداری {{ $context['county']['name'] }} اعمال می‌شود.</p><a href="{{ $context['public_path'] }}" target="_blank" rel="noopener">مشاهدهٔ سایت ↗</a></header>
    <p data-county-portal-status class="portal-county-settings-status" role="status" aria-live="polite">در حال دریافت تنظیمات…</p><button type="button" data-county-portal-retry hidden>تلاش دوباره</button>
    <form data-county-portal-form>
        <fieldset data-county-portal-fields disabled>
            <section class="portal-county-settings-section"><h2>معرفی و اطلاعات تماس</h2><div class="portal-county-settings-grid">
                <label class="portal-resource-field">عنوان پرتال<input name="title" data-field="title" required maxlength="255"></label>
                <label class="portal-resource-field">ایمیل<input type="email" name="email" data-field="email" dir="ltr" maxlength="255"></label>
                @foreach(['phone'=>'تلفن','fax'=>'نمابر','sms_number'=>'شمارهٔ پیامک'] as $key=>$label)<label class="portal-resource-field">{{ $label }}<input name="{{ $key }}" data-field="{{ $key }}" dir="ltr" maxlength="40"></label>@endforeach
                <label class="portal-resource-field">پیوند ویدئوی معرفی<input type="url" name="video_url" data-field="video_url" dir="ltr" maxlength="2048"></label>
                <label class="portal-resource-field portal-resource-wide">نشانی<textarea name="address" data-field="address" rows="3" maxlength="10000"></textarea></label>
                <label class="portal-resource-field portal-resource-wide">معرفی کوتاه شهرستان<textarea name="county_description" data-field="county_description" rows="4" maxlength="16000"></textarea></label>
                <div class="portal-resource-field portal-resource-wide"><label for="county-about-body">معرفی کامل شهرستان</label><textarea id="county-about-body" name="about_body" data-field="about_body" data-rich-text rows="12" maxlength="1000000"></textarea></div>
            </div></section>
            <section class="portal-county-settings-section"><h2>لوگو و تصاویر</h2><p class="portal-field-help">با برداشتن تصویر اختصاصی، طرح پیش‌فرض نمایش داده می‌شود. تصویر شعار سالِ پیش‌فرض از استانداری دریافت می‌شود.</p><div class="portal-county-settings-grid">
                @foreach(\App\Services\CountyPortal::IMAGES as $field=>$label)@include('admin.fields.county-image')@endforeach
                <label class="portal-resource-field portal-resource-wide">متن زیر منو<input name="slogan_text" data-field="slogan_text" maxlength="500"></label>
            </div></section>
            <section class="portal-county-settings-section"><h2>نمایش بخش‌ها</h2><div class="portal-county-settings-grid portal-county-section-choices">
                @foreach(\App\Services\CountyPortal::SECTIONS as $key=>$label)<label class="portal-choice"><input type="checkbox" name="section_{{ $key }}" data-field="section_{{ $key }}" checked><span>{{ $label }}</span></label>@endforeach
            </div></section>
            <details class="portal-county-settings-section"><summary>عنوان بخش‌های صفحه</summary><div class="portal-county-settings-grid">
                @foreach(\App\Services\CountyPortal::TITLES as $key=>$label)<label class="portal-resource-field">{{ $label }}<input name="title_{{ $key }}" data-field="title_{{ $key }}" required maxlength="100"></label>@endforeach
            </div></details>
            <div class="portal-editor-actions"><button type="submit" class="portal-button" disabled>ذخیرهٔ تغییرات پرتال</button><a href="{{ $context['dashboard_path'] }}">بازگشت به داشبورد</a></div>
        </fieldset>
    </form>
    <section class="portal-county-settings-section"><h2>منوها و دسترسی‌ها</h2><div class="portal-county-menu-actions">
        @foreach(\App\Services\CountyPortal::MENUS as $group=>$menu)<button type="button" data-county-prepare-menu="{{ $group }}">ویرایش {{ $menu[1] }} <span aria-hidden="true">←</span></button>@endforeach
    </div></section>
</div>
