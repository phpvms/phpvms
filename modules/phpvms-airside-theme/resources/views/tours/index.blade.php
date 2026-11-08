@extends('app')

@section('title', 'Tours')

@section('content')
<x-vms::page name="tours" label="Tours">
    <livewire:tours::tour-list />

    {{-- The bid drawer: tour cards open it with assignment-drawer.open, and the list reloads on assignment-drawer.closed. --}}
    <livewire:bids::assignment-drawer />
</x-vms::page>
@endsection
