<div class="portal-article-layout portal-article-page">
    <article class="portal-content-article portal-service-agreement portal-service-detail" aria-label="{{ $content['title'] }}" data-service-agreement="{{ $agreementKey }}">
        <div class="portal-article-body portal-rich-text portal-service-prose">{!! \App\Services\Html::articleBody($content['body'] ?? '') !!}</div>
    </article>
</div>
