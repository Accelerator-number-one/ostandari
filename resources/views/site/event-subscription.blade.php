<details class="portal-event-subscription" @click.outside="$el.open=false" @keydown.escape.prevent="$el.open=false;$el.querySelector('summary').focus()" @if(request()->boolean('unsubscribe'))open @endif x-data="eventSubscription(@js($county['slug'] ?? ''),{{ request()->boolean('unsubscribe')?'true':'false' }})">
    <summary>خبرم کن <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></summary>
    <form @submit.prevent="submit()" class="portal-event-form">
        <h3 x-text="unsubscribe?'لغو عضویت پیامکی':'اطلاع‌رسانی پیامکی رویدادها'">اطلاع‌رسانی پیامکی رویدادها</h3>
        <fieldset :disabled="busy">
            <label>شمارهٔ همراه<input name="mobile" x-model="mobile" type="tel" inputmode="tel" autocomplete="tel-national" dir="ltr" maxlength="11" placeholder="09xxxxxxxxx" required :readonly="!!challenge"></label>
            <template x-if="!challenge&&!unsubscribe"><label class="portal-event-consent"><input type="checkbox" x-model="consent" required><span>مایلم رویدادهای جدید {{ $county ? 'فرمانداری '.$county['name'] : 'استان' }} را از طریق پیامک دریافت کنم.</span></label></template>
            <div x-show="challenge" x-cloak><label>کد تأیید<input name="code" x-model="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" :required="!!challenge"></label><p x-show="devCode" x-cloak>کد محیط توسعه: <bdi x-text="devCode"></bdi></p></div>
            <button type="submit" class="portal-button" :disabled="busy" x-text="busy?'در حال بررسی…':(challenge?'تأیید کد':'دریافت کد تأیید')">دریافت کد تأیید</button>
        </fieldset>
        <p class="portal-event-success" role="status" x-show="message" x-text="message" x-cloak></p>
        <p class="portal-event-error" role="alert" x-show="error" x-text="error" x-cloak></p>
        <button class="portal-event-switch" type="button" @click="unsubscribe=!unsubscribe;challenge='';code='';error='';message=''" x-text="unsubscribe?'عضویت در اطلاع‌رسانی':'لغو عضویت پیامکی'">لغو عضویت پیامکی</button>
    </form>
</details>
