@php($dutyGroups=collect($items)->groupBy(fn($item)=>($item['duty_group']??null)?:'سایر واحدها'))
<div class="portal-duties-directory">
    <header class="portal-directory-heading">
        <span class="portal-directory-icon" aria-hidden="true">@include('icons.document-outline')</span>
        <h1>{{ $title }}</h1>
        <span class="portal-directory-count">{{ count($items) }} واحد</span>
    </header>
    @if($dutyGroups->isNotEmpty())
    <div class="portal-duties-layout">
        <aside class="portal-duty-navigation" aria-label="دسته‌بندی واحدها">
            <h2>دسته‌بندی واحدها</h2>
            <nav>@foreach($dutyGroups as $group=>$units)<a href="#duty-group-{{ $loop->index }}"><span>{{ $group }}</span><bdi>{{ $units->count() }}</bdi></a>@endforeach</nav>
        </aside>
        <div class="portal-duty-groups">
            @foreach($dutyGroups as $group=>$units)
            <section class="portal-duty-group" id="duty-group-{{ $loop->index }}" aria-labelledby="duty-heading-{{ $loop->index }}">
                <header><h2 id="duty-heading-{{ $loop->index }}">{{ $group }}</h2><span>{{ $units->count() }} واحد</span></header>
                <div class="portal-duties-grid">
                    @foreach($units as $item)
                    <a class="portal-duty-item" href="{{ $ui->article($item,$county) }}">
                        <span class="portal-duty-symbol" aria-hidden="true">@include('icons.document-outline')</span>
                        <div class="portal-duty-copy">
                            <h3>{{ $item['title'] }}</h3>
                            @if(!empty($item['excerpt']))<p>{{ $ui->text($item['excerpt']) }}</p>@endif
                            <span class="portal-duty-read">مشاهدهٔ شرح وظایف <span aria-hidden="true">←</span></span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
            @endforeach
        </div>
    </div>
    @else
        <p class="portal-directory-empty">شرح وظایفی منتشر نشده است.</p>
    @endif
</div>
@if($meta && $meta['last_page']>1)<nav class="portal-pagination" aria-label="صفحه‌های شرح وظایف">@for($number=max(1,$meta['current_page']-3);$number<=min($meta['last_page'],$meta['current_page']+3);$number++)<a href="{{ request()->fullUrlWithQuery(['page'=>$number]) }}" @if($number===$meta['current_page'])aria-current="page"@endif>{{ $number }}</a>@endfor</nav>@endif
