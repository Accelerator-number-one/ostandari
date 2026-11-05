@php
    $custom=$ui->image($organization['banner'] ?? null);
    $provided='/design/county-banners/'.$county['slug'].'.png';
    $provided=$custom ?? (is_file(public_path($provided))?$provided:null);
    $bannerTitle='فرمانداری شهرستان '.$county['name'];
@endphp
@if($provided)
    <img class="icx-responsive-img" src="{{ $provided }}" width="1380" height="180" alt="{{ $portalName }}" fetchpriority="high">
@else
    <div class="county-banner-template portal-county-banner" role="img" aria-label="{{ $portalName }}">
        <svg viewBox="0 0 1380 180" preserveAspectRatio="xMidYMid meet" aria-hidden="true"><defs><pattern id="county-texture-{{ $county['slug'] }}" width="64" height="180" patternUnits="userSpaceOnUse"><image href="/design/county-banners/amol.png" width="1380" height="180"/></pattern></defs><image href="/design/county-banners/amol.png" width="1380" height="180"/><rect x="420" width="550" height="180" fill="url(#county-texture-{{ $county['slug'] }})"/></svg>
        <div class="portal-county-banner-title" @if(mb_strlen($bannerTitle)>30)style="--county-title-size:2.6vw"@endif>{{ $bannerTitle }}</div>
    </div>
@endif
