<div class="source-gallery-grid">
@forelse($items as $gallery)
<section class="portal-card"><h2><a href="{{ $ui->href('/gallery?id='.$gallery['id'],$county) }}">{{ $gallery['title'] }}</a></h2><p>{{ $ui->text($gallery['description'] ?? '') }}</p><div class="gal-grid">
    @foreach($gallery['items'] ?? [] as $photo)
        @php($media=$photo['image'] ?? [])
        @php($url=$media['url'] ?? null)
        @if($url && str_starts_with($media['mime']??'','video/'))
            <figure><video controls preload="metadata" class="w-full rounded-xl" src="{{ $ui->href($url) }}"></video><figcaption>{{ $photo['caption'] ?? $photo['title'] ?? '' }}</figcaption></figure>
        @elseif($url && str_starts_with($media['mime']??'','audio/'))
            <figure><audio controls preload="none" class="w-full" src="{{ $ui->href($url) }}"></audio><figcaption>{{ $photo['caption'] ?? $photo['title'] ?? '' }}</figcaption></figure>
        @elseif($image=$ui->image($photo))
            <a class="gal-item" href="{{ $image }}" aria-label="{{ $photo['title'] ?? $gallery['title'] }}"><div class="gal-media"><img src="{{ $image }}" alt="{{ $photo['caption'] ?? $gallery['title'] }}" loading="lazy"></div><p class="gal-caption">{{ $photo['caption'] ?? '' }}</p></a>
        @endif
    @endforeach
</div></section>
@empty<p class="portal-empty">گالری‌ای منتشر نشده است.</p>@endforelse
</div>
<div id="lightboxOverlay" class="lightbox-overlay" role="dialog" aria-modal="true" aria-label="نمایش تصویر گالری"><div class="lightbox-box"><button type="button" id="lightboxClose" class="lightbox-close" aria-label="بستن تصویر">×</button><button type="button" id="lightboxPrev" class="lightbox-nav lightbox-prev" aria-label="تصویر قبلی">›</button><button type="button" id="lightboxNext" class="lightbox-nav lightbox-next" aria-label="تصویر بعدی">‹</button><div id="lightboxMedia" class="lightbox-media"></div><div class="lightbox-info"><p id="lightboxCaption"></p></div></div></div>
