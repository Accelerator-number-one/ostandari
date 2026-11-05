<div class="portal-menu-columns">
    @foreach(!empty($branch['children']) ? $branch['children'] : [$branch] as $group)
        <section class="portal-menu-group">
            <a class="portal-menu-group-title" href="{{ $group['href'] }}">@include('partials.custom-link-image',['link'=>$group]){{ $group['title'] }}</a>
            @if(!empty($group['children']))
                @include('partials.mega-links',['links'=>$group['children']])
            @endif
        </section>
    @endforeach
</div>
