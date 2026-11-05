@extends('layouts.base')
@section('body')
<div x-data="portalPage" data-design="{{ $page['scope'] }}" data-preview="false" data-section="citizen" data-path="/citizen" class="min-h-screen w-full font-vazirmatn">
    @include('legacy.SiteDashbordKarbaran')
</div>
@endsection
