@php
    $image=$ui->image($content);
    $kind=['news'=>'خبر','announcement'=>'اطلاعیه','resolution'=>'مصوبه','document'=>'سند','event'=>'رویداد','duty'=>'شرح وظایف'][$content['type'] ?? 'news'] ?? 'مطلب';
    $citizen=auth()->check() && auth()->user()->is_active && auth()->user()->person_type==='citizen';
@endphp
<div class="portal-article-layout portal-article-page">
    <article class="portal-content-article">
        <header class="portal-article-heading">
            <div class="portal-article-intro-row">
                <div class="portal-article-labels"><span>{{ $county?'فرمانداری شهرستان '.$county['name']:'استانداری مازندران' }}</span><span>{{ $content['category']['name'] ?? $kind }}</span></div>
                <div class="portal-article-actions portal-article-toolbar" aria-label="ابزارهای مطلب">
                    <button type="button" data-command="copyLink">@include('icons.link') کپی لینک</button>
                    <button type="button" data-command="sharePage">@include('icons.arrow') اشتراک‌گذاری</button>
                    <button type="button" data-command="printSection"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5M7 17H4V8h16v9h-3M7 14h10v7H7Z"/></svg>چاپ {{ $kind }}</button>
                </div>
            </div>
            <h1>{{ $content['title'] }}</h1>
            @if(!empty($content['subtitle']))<p class="portal-article-subtitle">{{ $content['subtitle'] }}</p>@endif
            <div class="portal-article-meta"><time datetime="{{ $content['published_at'] ?? '' }}">{{ $ui->date($content['published_at'] ?? null) }}</time></div>
        </header>
        @if(!empty($content['excerpt']))<p class="portal-article-summary">{{ $ui->text($content['excerpt']) }}</p>@endif
        @if($image)<figure class="portal-article-cover"><img src="{{ $image }}" alt="{{ $content['title'] }}" fetchpriority="high"></figure>@endif
        <div class="portal-article-body portal-rich-text">{!! \App\Services\Html::articleBody($content['body'] ?? '') !!}</div>
    </article>
    @if(!empty($related))
        <section class="portal-related-content portal-related-cards" aria-labelledby="related-title">
            <h2 id="related-title">مطالب مرتبط</h2>
            <div class="portal-news-grid">@foreach($related as $item)@include('site.news-card')@endforeach</div>
        </section>
    @endif
</div>
@if($content['comments_enabled'] ?? false)
    <section class="portal-comments" id="comments" aria-labelledby="comments-title">
        <header><h2 id="comments-title">دیدگاه‌ها</h2><span>{{ count($comments ?? []) }}</span></header>
        <div class="portal-comment-list">
            @forelse($comments ?? [] as $comment)
                <article class="portal-comment-entry"><div class="portal-comment-person" aria-hidden="true">@include('icons.people')</div><div><header><strong>{{ $comment['author']['name'] ?? 'شهروند' }}</strong><time datetime="{{ $comment['created_at'] ?? '' }}">{{ $ui->date($comment['created_at'] ?? null) }}</time></header><p>{{ $comment['body'] }}</p>@if(!empty($comment['reply']))<blockquote><strong>پاسخ روابط عمومی</strong><p>{{ $comment['reply'] }}</p></blockquote>@endif</div></article>
            @empty<p class="portal-comments-empty">دیدگاهی ثبت نشده است.</p>@endforelse
        </div>
        @if($citizen)
            <details class="portal-comment-compose"><summary><span>@include('icons.mail') دیدگاه و نظر شما</span><span aria-hidden="true">＋</span></summary>
                <form class="portal-form" x-data="apiForm(@js('contents/'.$content['id'].'/comments'))" @submit.prevent="submit($event)">
                    <label for="comment-body-{{ $content['id'] }}">متن دیدگاه<textarea id="comment-body-{{ $content['id'] }}" name="body" required minlength="3" maxlength="5000" rows="5"></textarea></label>
                    <div class="portal-comment-submit"><span>{{ auth()->user()->name }}</span><button class="portal-button" :disabled="busy">ثبت دیدگاه</button></div>
                    <p role="alert" x-text="error" x-show="error" x-cloak></p><p role="status" x-text="message" x-show="message" x-cloak></p>
                </form>
            </details>
        @else
            <div class="portal-comment-login"><span>برای ثبت دیدگاه وارد حساب شهروندی شوید.</span><a class="portal-button" href="/login?return={{ rawurlencode(request()->getRequestUri().'#comments') }}">ورود شهروندان</a></div>
        @endif
    </section>
@endif
