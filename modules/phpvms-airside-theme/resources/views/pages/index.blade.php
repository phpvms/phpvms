@extends('app')

@section('title', $page->name)

@section('content')
    <x-airside::page name="content-page" label="{{ $page->name }}">
        <x-airside::page-header :title="$page->name" />

        <article class="pv-prose max-w-3xl text-[14px] leading-relaxed text-ink">
            {!! $page->body !!}
        </article>
    </x-airside::page>
@endsection
