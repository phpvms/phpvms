@extends('app')

@section('title', trans_choice('common.download', 2))

@section('content')
    <x-airside::page name="downloads" label="Downloads">
        <x-airside::page-header headline="Resources" :title="trans_choice('common.download', 2)" />

        @if (! $grouped_files || count($grouped_files) === 0)
            <x-airside::empty icon="download" :title="__('downloads.none')" />
        @else
            @foreach ($grouped_files as $group => $files)
                <x-airside::card :title="$group" wire:key="group-{{ $loop->index }}">
                    <x-airside::file-list :files="$files" />
                </x-airside::card>
            @endforeach
        @endif
    </x-airside::page>
@endsection
