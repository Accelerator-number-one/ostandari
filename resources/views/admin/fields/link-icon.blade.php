<section class="portal-resource-field portal-icon-picker" x-data="{query:'',chosen:'',normalize(value){return value.toLowerCase().replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/^(?:آیکون|ایکون|icon)\s*/u,'').trim()}}" @portal:fields-filled.window="chosen=$event.detail.icon||''" x-init="$el.closest('form').addEventListener('change',event=>{if(event.target.matches('[data-image-upload],[data-image-value]'))chosen=''})" aria-labelledby="link-icon-label">
    <header><label id="link-icon-label" for="link-icon-search">آیکون پیوند</label><span class="portal-field-requirement">اختیاری</span></header>
    <input type="hidden" name="icon" data-field="icon" :value="chosen">
    <input type="search" id="link-icon-search" placeholder="جستجو با نام؛ مثلاً خانه یا home" x-model="query" autocomplete="off">
    <div class="portal-icon-grid" role="group" aria-label="انتخاب آیکون">
        @foreach(\App\Services\PortalIcons::ITEMS as $key=>$icon)
        <button type="button" x-show="!query||normalize(@js($icon[0].' '.$icon[1])).includes(normalize(query))" :aria-pressed="chosen==='{{ $key }}'" @click="$el.closest('form').querySelector('[data-image-remove]')?.click();chosen='{{ $key }}'" title="{{ $icon[0] }}"><img src="/design/icons/{{ $key }}.svg" alt="" width="26" height="26"><span>{{ $icon[0] }}</span></button>
        @endforeach
    </div>
    <button type="button" class="portal-icon-clear" @click="chosen='';$el.closest('form').querySelector('[data-image-remove]')?.click()">بدون آیکون</button>
    <p class="portal-field-help">برای تصویر دلخواه، از بخش تصویر زیر بارگذاری یا از گالری انتخاب کنید.</p>
</section>
