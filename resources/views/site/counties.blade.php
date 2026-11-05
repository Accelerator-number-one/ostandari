@include('site.county-map')
<section class="county-directory" aria-labelledby="county-directory-title">
    <h2 id="county-directory-title">فهرست فرمانداری‌ها</h2>
    <ul class="county-directory-grid">
        @foreach($ui->data('frontend')['counties'] as $entry)
            <li>
                <a href="/{{ $entry['slug'] }}" class="county-directory-card">
                    <span class="county-directory-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-7h6v7"/></svg></span>
                    <span class="county-directory-label"><span>فرمانداری شهرستان</span><strong>{{ $entry['name'] }}</strong></span>
                    <span class="county-directory-arrow" aria-hidden="true">←</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
