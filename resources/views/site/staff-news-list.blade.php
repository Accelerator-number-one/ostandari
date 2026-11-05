<h2>{{ \App\Services\StaffPortals::KINDS[$kind] }}</h2>
<div class="staff-news-list">
    @forelse($feed as $row)
        <article class="staff-news-card">
            <a href="{{ $portal->publicPath() }}/content/{{ $row->id }}" class="staff-news-image">
                @if($row->asset)<img src="{{ $row->asset->url }}" alt="{{ $row->title }}" loading="lazy">@else<span class="staff-news-placeholder" aria-hidden="true">@include('icons.document')</span>@endif
            </a>
            <div>
                <time datetime="{{ $row->published_at->toIso8601String() }}">{{ $ui->date($row->published_at->toIso8601String()) }}</time>
                <h3><a href="{{ $portal->publicPath() }}/content/{{ $row->id }}">{{ $row->title }}</a></h3>
                <p>{{ $row->summary ?: $ui->text($row->body ?? '') }}</p>
                <a class="staff-news-more" href="{{ $portal->publicPath() }}/content/{{ $row->id }}">ادامه مطلب <span aria-hidden="true">←</span></a>
            </div>
        </article>
    @empty
        <p class="staff-empty">موردی ثبت نشده است.</p>
    @endforelse
</div>
{{ $feed->links() }}
