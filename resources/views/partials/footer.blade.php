<div class="footer" data-source-footer="true">
<div class="top-footer">
<div class="iconostandari">
<svg style="margin-top:20px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="150" height="162" viewBox="0 0 150 162" fill="none" aria-hidden="true" focusable="false">
<g filter="url(#footer-original-_R_5_-filter0_d_1_209)">
<path d="M70.3887 3.91504C73.2292 2.2204 76.7708 2.2204 79.6113 3.91504L134.563 36.6992C137.285 38.3231 138.952 41.2594 138.952 44.4287V110.571C138.952 113.741 137.285 116.677 134.563 118.301L79.6113 151.085C76.7708 152.78 73.2292 152.78 70.3887 151.085L15.4365 118.301C12.7148 116.677 11.0479 113.741 11.0479 110.571V44.4287C11.0479 41.2594 12.7148 38.3231 15.4365 36.6992L70.3887 3.91504Z" fill="white" stroke="#CFCFCF" stroke-width="2">
</path>
<rect x="28.5" y="29.5" width="93" height="93" fill="url(#footer-original-_R_5_-pattern0_1_209)">
</rect>
</g>
<defs>
<filter id="footer-original-_R_5_-filter0_d_1_209" x="6.0481" y="1.64442" width="137.904" height="159.711" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix">
</feFlood>
<feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha">
</feColorMatrix>
<feOffset dy="4">
</feOffset>
<feGaussianBlur stdDeviation="2">
</feGaussianBlur>
<feComposite in2="hardAlpha" operator="out">
</feComposite>
<feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0">
</feColorMatrix>
<feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_1_209">
</feBlend>
<feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_1_209" result="shape">
</feBlend>
</filter>
<pattern id="footer-original-_R_5_-pattern0_1_209" patternContentUnits="objectBoundingBox" width="1" height="1">
<use xlink:href="#footer-original-_R_5_-image0_1_209" transform="scale(0.00195312)">
</use>
</pattern>
<image id="footer-original-_R_5_-image0_1_209" width="512" height="512" preserveAspectRatio="none" xlink:href="{{ $ui->href('/design/shared/7ce925f0576fccad43cd.png',$county) }}">
</image>
</defs>
</svg>
</div>
<footer class="icx-footer-root" aria-label="{{ $county?'پاورقی فرمانداری '.$county['name']:'پاورقی استانداری مازندران' }}">
<div class="icx-footer-wrap">
@if($county&&($footerMenu=$ui->menu($home['menus']??[],'portal-footer'))!==null)
@include('partials.county-footer-links',['entries'=>$footerMenu['items']??[]])
@else
<nav class="icx-footer-col" aria-label="سایت">
<h4 class="icx-footer-title">سایت</h4>
<ul class="icx-footer-list">
<li>
<a class="icx-footer-link" href="{{ $ui->href('/polls',$county) }}">نظرسنجی دستگاه‌های اجرایی</a>
</li>
<li>
<a class="icx-footer-link" href="{{ $ui->href('/transparency',$county) }}">سامانه شفافیت</a>
</li>
<li>
<a class="icx-footer-link" href="{{ $ui->href('/about',$county) }}">معرفی ما</a>
</li>
</ul>
</nav>
<nav class="icx-footer-col" aria-label="صفحات">
<h4 class="icx-footer-title">صفحات</h4>
<ul class="icx-footer-list">
<li>
<a class="icx-footer-link" href="{{ $ui->href('/',$county) }}">صفحه اصلی</a>
</li>
<li>
<a class="icx-footer-link" href="{{ $ui->href('/contact',$county) }}">تماس با ما</a>
</li>
</ul>
</nav>
<nav class="icx-footer-col" aria-label="سرویس‌ها">
<h4 class="icx-footer-title">سرویس‌ها</h4>
<ul class="icx-footer-list">
<li>
<a class="icx-footer-link" href="{{ $ui->href('/performance',$county) }}">عملکرد دستگاه</a>
</li>
<li>
<a class="icx-footer-link" href="{{ $ui->href('/privacy',$county) }}">بیانیه حریم خصوصی</a>
</li>
<li>
<a class="icx-footer-link" href="{{ $ui->href('/participation',$county) }}">بیانیه راهبرد مشارکت</a>
</li>
<li>
<a class="icx-footer-link" href="{{ $ui->href('/service-agreement/statement',$county) }}">بیانیه سطح توافق خدمات</a>
</li>
</ul>
</nav>@endif
@php($contact=$county?($organization??[]):($settings['site.contact']??$ui->data('official-site')['contact']))<address class="icx-footer-col icx-footer-contact">
<h4 class="icx-footer-title">{{ $county?'فرمانداری '.$county['name']:'اطلاعات تماس' }}</h4>@foreach(['address'=>'نشانی','email'=>'ایمیل','phone'=>'تلفن','fax'=>'نمابر'] as $key=>$label)@if(!empty($contact[$key]))<p class="icx-footer-text">
<span>{{ $label }}: </span>
<bdi>{{ $contact[$key] }}</bdi>
</p>@endif @endforeach</address>
@if($customFooterLinks=$ui->customLinks($home,'footer',$county))<nav class="portal-custom-footer-links" aria-label="پیوندهای صفحات">@foreach($customFooterLinks as $link)<a href="{{ $link['href'] }}">@include('partials.custom-link-image')<span>{{ $link['title'] }}</span></a>@endforeach</nav>@endif
</div>
</footer>
</div>
<div class="bottom-footer">
<a href="https://sadrasoft.ir/" class="source-footer-credit">
<span>طراحی و توسعه یافته توسط</span>
<span class="source-company">صدرا نوین طبرستان</span>
</a>
</div>
</div>