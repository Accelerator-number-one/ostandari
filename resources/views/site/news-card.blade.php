@php
    $photo=$ui->image($item);
    $kind=['news'=>'خبر','announcement'=>'اطلاعیه','resolution'=>'مصوبه','event'=>'رویداد','duty'=>'شرح وظایف'][$item['type'] ?? 'news'] ?? 'مطلب';
@endphp
<article class="portal-content-card {{ $photo?'':'without-photo' }}">
    <a class="portal-content-card-link" href="{{ $ui->article($item,$county) }}">
        @if($photo)<div class="portal-content-card-image"><img src="{{ $photo }}" alt="" width="640" height="400" loading="lazy"></div>@endif
        <div class="portal-content-card-copy">
            <div class="portal-content-card-meta"><span>{{ (!$county&&($item['organization']['type']??'')==='county')?'فرمانداری '.($item['organization']['county']['name']??''):($item['category']['name']??$kind) }}</span><time datetime="{{ $item['published_at'] ?? '' }}">{{ $ui->date($item['published_at'] ?? null) }}</time></div>
            <h3 title="{{ $item['title'] }}">{{ $item['title'] }}</h3>
            @if(!empty($item['excerpt']))<p>{{ $ui->text($item['excerpt']) }}</p>@endif
            <span class="portal-content-card-more">مشاهدهٔ {{ $kind }} <span aria-hidden="true">←</span></span>
        </div>
    </a>
</article>
