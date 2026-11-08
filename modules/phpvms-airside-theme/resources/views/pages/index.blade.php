@extends('app')

@section('title', $page->name)

@section('content')
    <x-vms::page name="content-page" label="{{ $page->name }}">
        <x-vms::page.header :title="$page->name" />

        <article class="vms-prose max-w-3xl text-[14px] leading-relaxed">
            {!! $page->body !!}
        </article>
    </x-vms::page>
@endsection
