<section class="staff-slideshow" data-staff-slideshow aria-label="اسلایدشوی {{ $portal->title }}" aria-roledescription="اسلایدشو" x-data="portalCarousel({{ count($staffSlides) }})" @mouseenter="hovered=true" @mouseleave="hovered=false" @focusin="focused=true" @focusout="if(!$el.contains($event.relatedTarget))focused=false" @keydown.arrow-left.prevent="move(1)" @keydown.arrow-right.prevent="move(-1)">
    <div class="staff-slide-stage">
        @foreach($staffSlides as $i=>$slide)
            <div class="staff-slide" x-show="index==={{ $i }}" @if($i>0)x-cloak @endif role="group" aria-roledescription="اسلاید" aria-label="{{ $i+1 }} از {{ count($staffSlides) }}">
                @if($slide['url'])<a class="staff-slide-image" href="{{ $slide['url'] }}" aria-label="{{ $slide['title'] }}">@else<div class="staff-slide-image">@endif
                    <img src="{{ $slide['image'] }}" alt="{{ $slide['title'] ?: 'اسلاید '.($i+1) }}" width="1280" height="480" @if($i===0)fetchpriority="high"@else loading="lazy"@endif x-on:error.once="$el.src='/design/site/assets/image/baner.png'">
                @if($slide['url'])</a>@else</div>@endif
                @if($slide['caption'] && $slide['title'])<div class="staff-slide-caption"><h2>{{ $slide['title'] }}</h2></div>@endif
            </div>
        @endforeach
    </div>
    @if(count($staffSlides)>1)
        <button class="staff-slide-arrow staff-slide-previous" type="button" aria-label="اسلاید قبلی" @click="move(-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
        <button class="staff-slide-arrow staff-slide-next" type="button" aria-label="اسلاید بعدی" @click="move(1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg></button>
        <div class="staff-slide-controls"><div class="staff-slide-dots">@foreach($staffSlides as $i=>$slide)<button type="button" aria-label="نمایش اسلاید {{ $i+1 }}" :aria-pressed="index==={{ $i }}" @click="index={{ $i }}"></button>@endforeach</div><button class="staff-slide-pause" type="button" @click="paused=!paused" :aria-label="paused?'پخش اسلایدشو':'توقف اسلایدشو'" x-text="paused?'▶':'Ⅱ'"></button></div>
    @endif
</section>
