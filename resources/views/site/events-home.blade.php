<section class="portal-events-home" aria-labelledby="events-home-title" data-home-reveal="true">
    <header><h2 id="events-home-title">{{ $county?($home['county_portal']['titles']['events']??'رویدادهای شهرستان'):'رویدادهای استان' }}</h2><div><a href="{{ $ui->href('/events',$county) }}">همهٔ رویدادها ←</a>@include('site.event-subscription')</div></header>
    <div class="portal-event-grid">@forelse(array_slice($home['events'] ?? [],0,3) as $item)@include('site.news-card')@empty<p class="portal-events-empty">رویدادی منتشر نشده است.</p>@endforelse</div>
</section>
