<ul class="portal-menu-links">
    @foreach($links as $link)
        <li>
            <a href="{{ $link['href'] }}">{{ $link['title'] }}</a>
            @if(!empty($link['children']))
                @include('partials.mega-links',['links'=>$link['children']])
            @endif
        </li>
    @endforeach
</ul>
