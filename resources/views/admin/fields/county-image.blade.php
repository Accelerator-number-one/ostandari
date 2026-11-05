<section class="portal-resource-field portal-image-field" data-image-field="{{ $field }}" aria-labelledby="county-{{ $field }}-label">
    <header><label id="county-{{ $field }}-label" for="county-{{ $field }}-file">{{ $label }}</label><span class="portal-field-requirement">اختیاری</span></header>
    <input id="county-{{ $field }}-file" type="file" accept="image/png,image/jpeg,image/webp" data-image-upload="{{ $field }}">
    <figure data-image-preview hidden><img alt="{{ $label }}"><figcaption></figcaption></figure>
    <p data-image-status role="status" aria-live="polite" class="portal-field-help"></p>
    <div class="portal-image-actions"><button type="button" data-image-retry hidden>تلاش دوباره</button><button type="button" data-image-remove hidden>برداشتن تصویر اختصاصی</button></div>
    <details><summary>انتخاب از تصاویر همین فرمانداری</summary><label for="county-{{ $field }}">تصاویر قبلی</label><select id="county-{{ $field }}" name="{{ $field }}" data-field="{{ $field }}" data-image-value data-lookup="media"><option value="">بدون تصویر اختصاصی</option></select></details>
</section>
