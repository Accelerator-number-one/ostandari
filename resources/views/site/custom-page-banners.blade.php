@if($customBanners=$ui->customLinks($home,'banner',$county))
<section class="portal-custom-banners" aria-label="پیوندهای تصویری">@foreach($customBanners as $link)<a href="{{ $link['href'] }}">@include('partials.custom-link-image')<span>{{ $link['title'] }}</span></a>@endforeach</section>
@endif
