<section class="portal-useful-panel" aria-labelledby="useful-links-title">
    <header><h2 id="useful-links-title">پیوندهای مفید</h2><a href="{{ $ui->href('/links',$county) }}">همهٔ پیوندها ←</a></header>
    <div class="portal-useful-grid">
        @foreach([...array_slice($ui->usefulLinks($home['links'] ?? []),0,14), ...$ui->customLinks($home,'links',$county)] as $link)
            <a @class(['portal-useful-link','has-custom-image'=>($link['link_style']??'')==='image']) href="{{ $ui->href($link['url'] ?? $link['href'] ?? '/links',$county) }}">
                @if(($link['link_style']??'')==='image')
                    @include('partials.custom-link-image')
                @else
                    @include('partials.link-symbol')
                @endif
                <span>{{ $link['title'] }}</span><span aria-hidden="true">↖</span>
            </a>
        @endforeach
    </div>
</section>
