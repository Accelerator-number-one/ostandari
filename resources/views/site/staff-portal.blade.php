@php
    $manager=$portal->member;
    $portrait=$portal->portrait?->url ?: $ui->image($manager->toArray());
@endphp
<section class="staff-public staff-public-revised" aria-label="صفحه شخصی {{ $manager->name }}">
    @if(!$staffItem && !$staffPage)<h1 class="sr-only">صفحه شخصی {{ $manager->name }}</h1>@endif
    <div class="staff-public-layout {{ !$staffItem && !$staffPage?'staff-home-grid':'' }}">
        <div class="staff-public-content" @if(!$staffItem && !$staffPage)x-data="personalPortalTabs('{{ $staffTab }}')"@endif>
            @if($staffPage)
                <article class="staff-public-card staff-article staff-custom-page"><a class="staff-back" href="{{ $portal->publicPath() }}">صفحه اصلی <span aria-hidden="true">←</span></a><h1>{{ $staffPage->title }}</h1><div class="portal-article-body">{!! \App\Services\Html::articleBody($staffPage->body ?? '') !!}</div></article>
            @else
                @if($staffItem)
                    <article class="staff-public-card staff-article"><a class="staff-back" href="{{ $portal->publicPath() }}?tab={{ $staffItem->kind }}">بازگشت به {{ \App\Services\StaffPortals::KINDS[$staffItem->kind] }}</a><time datetime="{{ $staffItem->published_at->toIso8601String() }}">{{ $ui->date($staffItem->published_at->toIso8601String()) }}</time><h1>{{ $staffItem->title }}</h1>@if($staffItem->summary)<p class="staff-article-lead">{{ $staffItem->summary }}</p>@endif @if($staffItem->asset)<img class="staff-article-image" src="{{ $staffItem->asset->url }}" alt="{{ $staffItem->title }}">@endif<div class="portal-article-body">{!! \App\Services\Html::articleBody($staffItem->body ?? '') !!}</div></article>
                @else
                    <nav class="staff-public-tabs" role="tablist" aria-label="بخش‌های صفحه شخصی" x-ref="tabs">
                        @foreach(['news'=>'اخبار','announcement'=>'اطلاعیه‌ها','about'=>$staffAboutLabel] as $key=>$label)
                            <button type="button" role="tab" id="staff-tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="staff-panel-{{ $key }}"
                                aria-selected="{{ $staffTab===$key?'true':'false' }}" :aria-selected="tab==='{{ $key }}'"
                                tabindex="{{ $staffTab===$key?'0':'-1' }}" :tabindex="tab==='{{ $key }}'?0:-1" @click="select('{{ $key }}')"
                                @keydown.arrow-left.prevent="move(1)" @keydown.arrow-right.prevent="move(-1)"
                                @keydown.home.prevent="select('news',true)" @keydown.end.prevent="select('about',true)">{{ $label }}</button>
                        @endforeach
                    </nav>
                    <div class="staff-tab-panels" id="staff-tab-content">
                        @foreach(['news','announcement','about'] as $key)
                            <section class="staff-public-card staff-tab-panel" role="tabpanel" id="staff-panel-{{ $key }}" aria-labelledby="staff-tab-{{ $key }}" tabindex="0"
                                x-show="tab==='{{ $key }}'" x-transition.opacity.duration.150ms @if($staffTab!==$key)x-cloak @endif>
                                @if($key==='about')
                                    <h2>{{ $staffAboutLabel }}</h2>
                                    @if($portal->summary)<p class="staff-article-lead">{{ $portal->summary }}</p>@endif
                                    @if($portal->body)<div class="portal-article-body">{!! \App\Services\Html::articleBody($portal->body) !!}</div>@endif
                                @else
                                    @include('site.staff-news-list',['kind'=>$key,'feed'=>$staffFeeds[$key]])
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
        @if(!$staffItem && !$staffPage)@include('site.staff-slider')@endif
        <aside class="staff-public-profile">
            <section class="staff-public-card staff-manager-card"><div class="staff-manager-photo" x-data="{failed:false}">@if($portrait)<img src="{{ $portrait }}" alt="{{ $manager->name }}" x-show="!failed" x-on:error="failed=true" x-init="failed=$el.complete && !$el.naturalWidth">@endif<span @if($portrait)x-show="failed" x-cloak @endif class="staff-avatar" aria-hidden="true">@include('icons.people')</span></div><h2>{{ $manager->name }}</h2>@if($portal->title!==$manager->name)<p>{{ $portal->title }}</p>@endif @if($portal->email)<a class="staff-direct-contact" href="mailto:{{ $portal->email }}">ارتباط مستقیم</a>@elseif($portal->phone)<a class="staff-direct-contact" href="tel:{{ preg_replace('/[^+0-9]/','',$portal->phone) }}">ارتباط مستقیم</a>@endif</section>
            @if($staffLinks->isNotEmpty())<section class="staff-public-card"><h2>پیوندها</h2><ul class="staff-links-list">@foreach($staffLinks as $link)<li><a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">@if($link->asset)<img src="{{ $link->asset->url }}" alt="" loading="lazy">@endif<span>{{ $link->title }}</span><span aria-hidden="true">↗</span></a></li>@endforeach</ul></section>@endif
            @if($portal->phone || $portal->email || $portal->address)<section class="staff-public-card"><h2>اطلاعات تماس</h2><dl class="staff-contact">@if($portal->phone)<div><dt>تلفن</dt><dd><a href="tel:{{ preg_replace('/[^+0-9]/','',$portal->phone) }}"><bdi>{{ $portal->phone }}</bdi></a></dd></div>@endif @if($portal->email)<div><dt>ایمیل</dt><dd><a href="mailto:{{ $portal->email }}"><bdi>{{ $portal->email }}</bdi></a></dd></div>@endif @if($portal->address)<div><dt>نشانی</dt><dd>{{ $portal->address }}</dd></div>@endif</dl></section>@endif
        </aside>
    </div>
    @if(!$staffItem && !$staffPage)
        <section class="staff-media-section" id="staff-gallery" aria-labelledby="staff-gallery-title"><header><span class="staff-media-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 6-6 4 4 3-3 5 5"/></svg></span><h2 id="staff-gallery-title">گالری تصاویر</h2></header><div class="staff-media-grid">@forelse($staffPhotos as $row)<a class="staff-gallery-item" href="{{ $row->asset?->url }}" target="_blank" rel="noopener noreferrer"><div><img src="{{ $row->asset?->url }}" alt="{{ $row->title }}" loading="lazy" width="400" height="260"></div><h3>{{ $row->title }}</h3></a>@empty<p class="staff-empty">تصویری منتشر نشده است.</p>@endforelse</div>{{ $staffPhotos->links() }}</section>
        <section class="staff-media-section" id="staff-videos" aria-labelledby="staff-video-title"><header><span class="staff-media-icon" aria-hidden="true">▶</span><h2 id="staff-video-title">گالری ویدئو</h2></header><div class="staff-media-grid">@forelse($staffVideos as $row)<a class="staff-gallery-item" href="{{ $row->url }}" target="_blank" rel="noopener noreferrer"><div>@if($row->asset)<img src="{{ $row->asset->url }}" alt="{{ $row->title }}" loading="lazy" width="400" height="260">@endif<span class="staff-play" aria-hidden="true">▶</span></div><h3>{{ $row->title }}</h3></a>@empty<p class="staff-empty">ویدئویی منتشر نشده است.</p>@endforelse</div>{{ $staffVideos->links() }}</section>
    @endif
</section>
