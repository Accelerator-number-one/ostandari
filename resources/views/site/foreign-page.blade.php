@extends('layouts.base')
@section('body')
<div x-data="portalPage" @keydown.escape.window="dismiss()" data-design="{{ $page['scope'] }}" data-section="site" data-preview="false" data-path="{{ $page['path'] }}" class="min-h-screen w-full font-vazirmatn">
    @include('legacy.'.$page['id'])
</div>
@endsection
