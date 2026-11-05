@push('styles')<link rel="stylesheet" href="/styles/links-directory.css">@endpush
<section class="portal-all-links" aria-label="پیوندها" x-data="{query:'',selected:'',normalize(value){return String(value).replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/‌/g,' ').toLowerCase().trim()},matches(value){return !this.query.trim()||this.normalize(value).includes(this.normalize(this.query))}}">
    <div class="portal-all-links-filters">
        <label for="all-links-search">جستجوی پیوند<input type="search" id="all-links-search" x-model="query" autocomplete="off" placeholder="نام پیوند را بنویسید"></label>
        <label for="all-links-group">گروه پیوند<select id="all-links-group" x-model="selected"><option value="">همهٔ گروه‌ها</option>@foreach($linkGroups as $group)<option value="{{ $group['key'] }}">{{ $group['title'] }}</option>@endforeach</select></label>
    </div>
    @forelse($linkGroups as $group)
    <section class="portal-all-links-group" aria-labelledby="links-heading-{{ $loop->index }}" x-show="(!selected||selected===@js($group['key']))&&@js(array_column($group['items'],'title')).some(title=>matches(title))">
        <header><h2 id="links-heading-{{ $loop->index }}">{{ $group['title'] }}</h2><span>{{ count($group['items']) }}</span></header>
        <div class="portal-all-links-grid">
            @foreach($group['items'] as $link)
            <a class="portal-all-links-card" href="{{ $ui->href($link['url'] ?? $link['href'] ?? '/links',$county) }}" x-show="matches(@js($link['title']))">
                @if(($link['link_style']??'')==='image')
                    @include('partials.custom-link-image')
                @else
                    @include('partials.link-symbol')
                @endif
                <span class="portal-all-links-title">{{ $link['title'] }}</span><span class="portal-all-links-arrow" aria-hidden="true">←</span>
            </a>
            @endforeach
        </div>
    </section>
    @empty
        <p class="portal-feed-empty">پیوندی ثبت نشده است.</p>
    @endforelse
    @if($linkGroups)
        <p class="portal-feed-empty" x-cloak x-show="!@js($linkGroups).some(group=>(!selected||selected===group.key)&&group.items.some(link=>matches(link.title)))">پیوندی با این جستجو پیدا نشد.</p>
    @endif
</section>
