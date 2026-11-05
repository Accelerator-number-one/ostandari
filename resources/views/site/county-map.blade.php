@php
    $mapCounties = array_map(function ($entry) {
        $artwork = $entry['usesPreviewTemplate'] ? 'sari' : $entry['slug'];
        $entry['previewImage'] = "/images/county-previews/{$artwork}.webp";

        return $entry;
    }, $ui->data('frontend')['counties']);
    $initialCounty = collect($mapCounties)->firstWhere('slug', 'sari') ?? $mapCounties[0];
    $countyPreviews = array_map(fn ($entry) => \Illuminate\Support\Arr::only($entry, ['slug', 'name', 'previewImage', 'usesPreviewTemplate']), $mapCounties);
@endphp
<section class="portal-map-panel" id="counties" aria-labelledby="counties-title"
    x-data="{selected:@js($initialCounty['slug']),loadedImage:@js($initialCounty['previewImage']),counties:@js($countyPreviews),get current(){return this.counties.find(c=>c.slug===this.selected)||this.counties[0]}}">
    <header class="portal-map-heading">
        <div class="portal-map-title">
            <span class="portal-map-title-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21H4V8l8-5 8 5v13h-4M9 21v-7h6v7M8 10h.01M12 10h.01M16 10h.01"/></svg></span>
            <h2 id="counties-title">فرمانداری‌ها</h2>
            <span class="portal-map-count">{{ strtr((string) count($mapCounties), array_combine(range(0, 9), preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY))) }} شهرستان</span>
        </div>
        <div class="portal-map-picker">
            <label for="county-select">انتخاب فرمانداری</label>
            <div class="portal-map-select">
                <select id="county-select" x-model="selected">
                    @foreach($mapCounties as $entry)<option value="{{ $entry['slug'] }}" @selected($entry['slug'] === $initialCounty['slug'])>{{ $entry['name'] }}</option>@endforeach
                </select>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </div>
        </div>
    </header>
    <div class="portal-map-content">
        <article class="portal-map-preview">
            <a class="portal-map-photo county-artwork" href="/{{ $initialCounty['slug'] }}" :href="'/'+selected" :aria-label="'فرمانداری شهرستان '+current.name">
                <img src="{{ $initialCounty['previewImage'] }}" :src="current.previewImage" alt="" width="720" height="480" loading="lazy" decoding="async"
                    x-on:load="loadedImage=$el.getAttribute('src')" x-on:error="loadedImage=null"
                    :class="{'is-loading':loadedImage!==current.previewImage}">
                <span class="portal-county-nameplate" hidden :hidden="!current.usesPreviewTemplate || loadedImage!==current.previewImage" aria-hidden="true" x-text="'فرمانداری '+current.name"></span>
            </a>
            <div class="portal-map-preview-body">
                <h3 role="status" aria-live="polite" x-text="'فرمانداری شهرستان '+current.name">فرمانداری شهرستان {{ $initialCounty['name'] }}</h3>
                <a class="portal-map-enter" href="/{{ $initialCounty['slug'] }}" :href="'/'+selected">
                    <span>ورود به فرمانداری</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
                </a>
            </div>
        </article>
        <div class="portal-map-frame">
            <p class="portal-map-scroll-hint">برای دیدن همهٔ نام‌ها، نقشه را به چپ و راست بکشید.</p>
            <div class="portal-map-viewport">
                <svg viewBox="0 -28 645 308" role="group" aria-label="نقشه فرمانداری‌های مازندران">
                    <g>@foreach($mapCounties as $entry)
                        <a href="/{{ $entry['slug'] }}" class="county-region{{ $entry['slug'] === $initialCounty['slug'] ? ' is-selected' : '' }}" aria-label="ورود به فرمانداری {{ $entry['name'] }}"
                            @mouseenter="selected='{{ $entry['slug'] }}'" @focus="selected='{{ $entry['slug'] }}'" :class="{'is-selected':selected==='{{ $entry['slug'] }}'}">
                            <title>{{ $entry['name'] }}</title><path d="{{ $entry['shape'] }}" vector-effect="non-scaling-stroke"/>
                        </a>
                    @endforeach</g>
                    <g class="portal-map-guides" aria-hidden="true">
                        @foreach($mapCounties as $entry)
                            @if(!empty($entry['labelAnchor']))
                                @php
                                    $end = $entry['labelLeaderEnd'] ?? [$entry['label'][0], $entry['label'][1] + 9];
                                @endphp
                                <g :class="{'is-selected':selected==='{{ $entry['slug'] }}'}">
                                    <path d="M{{ implode(',', $entry['labelAnchor']) }} L{{ implode(',', $end) }}" vector-effect="non-scaling-stroke"/>
                                    <circle cx="{{ $entry['labelAnchor'][0] }}" cy="{{ $entry['labelAnchor'][1] }}" r="1.7"/>
                                </g>
                            @endif
                        @endforeach
                    </g>
                    <g class="portal-map-labels" aria-hidden="true">@foreach($mapCounties as $entry)
                        <a href="/{{ $entry['slug'] }}" tabindex="-1" @mouseenter="selected='{{ $entry['slug'] }}'" :class="{'is-selected':selected==='{{ $entry['slug'] }}'}">
                            <text x="{{ $entry['label'][0] }}" y="{{ $entry['label'][1] }}" text-anchor="middle" dominant-baseline="central" direction="rtl">{{ $entry['name'] }}</text>
                        </a>
                    @endforeach</g>
                </svg>
            </div>
        </div>
    </div>
    <details class="portal-map-directory">
        <summary><span>فهرست همهٔ فرمانداری‌ها</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
        <nav aria-label="فهرست فرمانداری‌های مازندران">@foreach($mapCounties as $entry)<a href="/{{ $entry['slug'] }}">{{ $entry['name'] }}<span aria-hidden="true">←</span></a>@endforeach</nav>
    </details>
</section>
