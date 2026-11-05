@if($serviceDetail??false)
    @foreach($items as $item)
    <article class="portal-service-detail">
        @if(!empty($item['code']))<span class="portal-service-code">شناسهٔ خدمت: <bdi>{{ $item['code'] }}</bdi></span>@endif
        @if(!empty($item['description']))<section class="portal-service-prose"><h2>معرفی خدمت</h2>{!! \App\Services\Html::articleBody($item['description']) !!}</section>@endif
        @if(collect(['delivery_time','cost','target_audience'])->contains(fn($key)=>isset($item[$key])&&$item[$key]!==''))
        <dl class="portal-service-facts">
            @foreach(['delivery_time'=>'زمان ارائهٔ خدمت','cost'=>'هزینه','target_audience'=>'مخاطبان'] as $key=>$label)
                @if(isset($item[$key])&&$item[$key]!=='')<div><dt>{{ $label }}</dt><dd>{{ $item[$key] }}</dd></div>@endif
            @endforeach
        </dl>
        @endif
        @foreach(['requirements'=>'مدارک موردنیاز','steps'=>'مراحل انجام خدمت'] as $key=>$label)
            @if(!empty($item[$key]))<section class="portal-service-prose"><h2>{{ $label }}</h2><ol class="portal-service-steps">@foreach((array)$item[$key] as $value)<li>{{ $value }}</li>@endforeach</ol></section>@endif
        @endforeach
        @if(!empty($item['url']))<footer class="portal-service-detail-footer"><a class="portal-service-launch" href="{{ $ui->href($item['url']) }}">ورود به خدمت <span aria-hidden="true">↗</span></a></footer>@endif
    </article>
    @endforeach
@else
    <div class="portal-services-grid">
        @forelse($items as $item)
        <article class="portal-service-card">
            <div class="portal-service-card-top"><span class="portal-service-symbol" aria-hidden="true">@include('icons.services-outline')</span>@if(!empty($item['code']))<span class="portal-service-code">شناسه: <bdi>{{ $item['code'] }}</bdi></span>@endif</div>
            <h2><a href="{{ $ui->href('/service/'.$item['id'],$county) }}">{{ $item['name'] }}</a></h2>
            @if(!empty($item['description']))<p>{{ $ui->text($item['description']) }}</p>@endif
            @if(!empty($item['category']['name']))<span class="portal-service-category">{{ $item['category']['name'] }}</span>@endif
            <a class="portal-service-card-link" href="{{ $ui->href('/service/'.$item['id'],$county) }}">مشاهدهٔ خدمت <span aria-hidden="true">←</span></a>
        </article>
        @empty
        <div class="portal-directory-empty"><span class="portal-directory-icon" aria-hidden="true">@include('icons.services-outline')</span><p>خدمتی در این بخش ثبت نشده است.</p></div>
        @endforelse
    </div>
@endif
