<div class="portal-duty-detail">
    <nav class="portal-duty-detail-nav" aria-label="شرح وظایف واحدها"><a href="{{ $ui->href('/duties',$county) }}">همه واحدها</a>@if(!empty($content['duty_group']))<span>{{ $content['duty_group'] }}</span>@endif</nav>
    @include('site.article')
    @if(!empty($content['metadata']['duty_source_id']) && !empty($content['metadata']['source_url']))<p class="portal-duty-source">منبع: <a href="{{ $content['metadata']['source_url'] }}" target="_blank" rel="noopener noreferrer">استانداری مازندران</a></p>@endif
</div>
