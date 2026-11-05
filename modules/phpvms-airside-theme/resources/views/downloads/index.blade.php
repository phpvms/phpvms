@extends('app')

@section('title', trans_choice('common.download', 2))

@section('content')
    <x-vms::page name="downloads" label="Downloads">
        <x-vms::page.header :title="trans_choice('common.download', 2)" />

        @if (! $grouped_files || count($grouped_files) === 0)
            <x-vms::ui.empty icon="download" :title="__('downloads.none')" />
        @else
            @foreach ($grouped_files as $group => $files)
                <x-ts-card :header="$group" wire:key="group-{{ $loop->index }}">
                    <x-vms::ui.file-list :files="$files" />
                </x-ts-card>
            @endforeach
        @endif
    </x-vms::page>
@endsection
