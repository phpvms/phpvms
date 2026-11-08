@extends('app')
@section('title', 'Addon page')
@section('css')
    <style id="addon-css">.addon { color: red; }</style>
@endsection
@section('scripts_head')
    <meta name="addon-head" content="1" />
@endsection
@section('content')
    <p id="addon-body">addon body</p>
@endsection
@section('scripts')
    <script id="addon-script">window.addonRuns = (window.addonRuns || 0) + 1;</script>
@endsection
