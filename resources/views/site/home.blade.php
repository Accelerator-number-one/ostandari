<div class="home-experience">
<h1 class="source-sr-only">درگاه الکترونیکی استانداری مازندران</h1>
<section class="home-intro" aria-label="تازه‌های استانداری">
<div class="container-body d-f6279acc73263f">@include('site.latest')<div class="container-body-right">@include('site.slider')</div>@include('site.governor')</div>
</section>
@include('site.custom-page-banners')
<div class="sections-container">
<section class="home-counties" aria-label="فرمانداری‌های مازندران" data-home-reveal="true">@include('site.county-map')</section>
<section class="home-information" aria-label="اخبار و پیوندهای مفید" data-home-reveal="true">
<div class="container-peyvand"><div class="portal-home-information">@include('site.news-tabs')@include('site.useful-links')</div></div>
</section>
@include('site.events-home')
<section class="home-managers" aria-labelledby="home-managers-title" data-home-reveal="true">
<div class="container-modiran">
<div class="home-section-heading">
<div>
<h2 id="home-managers-title">مدیران و مسئولان استان</h2>
</div>
<a href="{{ $ui->href('/managers',$county) }}" class="home-text-link">مشاهدهٔ همه <span aria-hidden="true">←</span>
</a>
</div>@include('site.home-managers')</div>
</section>
<section class="home-public-relations" aria-labelledby="home-media-title" data-home-reveal="true">
<div class="container-omomi">
<div class="stdprx-container">
<div class="stdprx-header home-section-heading">
<div>
<h2 id="home-media-title">روابط عمومی استانداری</h2>
</div>
<a href="{{ $ui->href('/news',$county) }}" class="home-text-link">آرشیو اخبار <span aria-hidden="true">←</span>
</a>
</div>
<div class="stdprx-body">
<div class="stdprx-tv">
<a class="stdprx-screen" href="{{ $ui->href('/gallery',$county) }}" aria-label="گالری روابط عمومی">@if($image=$ui->image(($home['galleries'][0]['items'][0] ?? [])))<img src="{{ $image }}" alt="" loading="lazy">@endif<div class="home-media-caption">
<span>مازندران در قاب تصویر</span>
<strong>{{ $home['galleries'][0]['title'] ?? 'روایت تصویری رویدادهای استان' }}</strong>
<span class="home-media-action">مشاهدهٔ گالری ←</span>
</div>
</a>
</div>
<div class="stdprx-timeline">
<ul class="stdprx-list">@foreach(array_slice(!empty($home['events'])?$home['events']:($home['news']??[]),0,6) as $item)<li class="stdprx-item">
<time>{{ $ui->date($item['published_at'] ?? null) }}</time>
<a href="{{ $ui->article($item,$county) }}">{{ $item['title'] }}</a>
</li>@endforeach</ul>
<div class="stdprx-actions">
<a class="stdprx-btn live" href="{{ $ui->href('/news',$county) }}">اخبار</a>
<a class="stdprx-btn archive" href="{{ $ui->href('/gallery',$county) }}">آرشیو</a>
</div>
</div>
</div>
</div>
</div>
</section>
<section class="home-gallery" aria-labelledby="home-gallery-title" data-home-reveal="true">
<div class="container-galery">
<section class="gallery-section">
<div class="home-section-heading">
<div>
<h2 class="gallery-title" id="home-gallery-title">گالری تصاویر</h2>
</div>
<a href="{{ $ui->href('/gallery',$county) }}" class="home-text-link">همهٔ گالری‌ها <span aria-hidden="true">←</span>
</a>
</div>
<div class="gallery-wrapper">@foreach($home['galleries'] ?? [] as $item)<article class="gallery-card1">
<div class="card1-header">
<a class="card1-title" href="{{ $ui->href('/gallery?id='.$item['id'],$county) }}">{{ $item['title'] }}</a>
<a class="jahatha" href="{{ $ui->href('/gallery?id='.$item['id'],$county) }}">↖</a>
</div>
<a class="image-box" href="{{ $ui->href('/gallery?id='.$item['id'],$county) }}">@if($image=$ui->image($item['items'][0] ?? []))<img src="{{ $image }}" alt="" loading="lazy">@endif<p class="caption">مشاهدهٔ گالری ←</p>
</a>
</article>@endforeach</div>
</section>
</div>
</section>
</div>
</div>