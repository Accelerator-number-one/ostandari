@if($entries)
<ul>
    @foreach($entries as $entry)
    <li>
        <a href="{{ $entry['href'] }}" @if(parse_url($entry['href'],PHP_URL_PATH)===request()->getPathInfo()&&!parse_url($entry['href'],PHP_URL_HOST))aria-current="page"@endif><span>{{ $entry['title'] }}</span><span aria-hidden="true">←</span></a>
        @if(!empty($entry['children']))@include('partials.service-menu-links',['entries'=>$entry['children']])@endif
    </li>
    @endforeach
</ul>
@endif
