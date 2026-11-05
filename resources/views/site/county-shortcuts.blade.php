<section class="portal-office-panel county-shortcuts-panel {{ !empty($leadCountyShortcuts)?'county-intro-shortcuts':'' }}">
    <header class="portal-office-panel-head"><h2>{{ $labels['shortcuts'] }}</h2></header>
    <div class="portal-office-panel-body portal-office-shortcuts">
        @foreach($quickLinks as $link)
            <a href="{{ $ui->href(!empty($link['content_id'])?'/content/'.$link['content_id']:($link['url']??'/'),$county) }}">
                @include('partials.custom-link-image')
                <span>{{ $link['title'] }}</span><span class="county-shortcut-arrow" aria-hidden="true">←</span>
            </a>
        @endforeach
    </div>
</section>
