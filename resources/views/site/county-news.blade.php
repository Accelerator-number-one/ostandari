<section class="portal-office-panel portal-office-news {{ !empty($compactCountyNews)?'county-intro-news':'' }}" aria-labelledby="county-news-title">
    <header class="portal-office-panel-head">
        <h2 id="county-news-title">{{ $labels['news'] }}</h2>
        <a href="{{ $ui->href('/news',$county) }}">همهٔ اخبار <span aria-hidden="true">←</span></a>
    </header>
    <div class="portal-office-panel-body">
        <div class="cb2-center" x-data="{tab:0}">
            <div class="news-top">
                <div class="news-tabs2" role="tablist" aria-label="اخبار فرمانداری" x-ref="countyNewsTabs">
                    @foreach(['اخبار','اطلاعیه‌ها'] as $i=>$label)
                        <button type="button" role="tab" class="news-tab-btn" id="county-news-tab-{{ $i }}" aria-controls="county-news-panel-{{ $i }}" :class="{'active':tab==={{ $i }}}" :aria-selected="tab==={{ $i }}" :aria-pressed="tab==={{ $i }}" :tabindex="tab==={{ $i }}?0:-1" @click="tab={{ $i }}" @keydown.arrow-left.prevent="tab=1-tab;$nextTick(()=>$refs.countyNewsTabs.querySelectorAll('[role=tab]')[tab].focus())" @keydown.arrow-right.prevent="tab=1-tab;$nextTick(()=>$refs.countyNewsTabs.querySelectorAll('[role=tab]')[tab].focus())">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @foreach(['news','announcements'] as $i=>$kind)
                <div class="news-list2 portal-county-card-grid" id="county-news-panel-{{ $i }}" role="tabpanel" aria-labelledby="county-news-tab-{{ $i }}" x-show="tab==={{ $i }}" @if($i>0)x-cloak @endif>
                    @forelse($home[$kind]??[] as $item)
                        @include('site.news-card')
                    @empty
                        <p class="source-empty">{{ $i===0?'خبری منتشر نشده است.':'اطلاعیه‌ای منتشر نشده است.' }}</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>
</section>
