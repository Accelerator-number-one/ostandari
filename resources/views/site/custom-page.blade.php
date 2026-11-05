<div class="portal-article-layout portal-article-page">
    <article class="portal-content-article portal-service-agreement portal-custom-document" aria-labelledby="custom-page-title" data-custom-page="{{ $customPage['id'] }}">
        <header class="portal-article-heading"><h1 id="custom-page-title">{{ $customPage['title'] }}</h1></header>
        <div class="portal-article-body portal-rich-text">{!! \App\Services\Html::articleBody($customPage['body']) !!}</div>
    </article>
</div>
