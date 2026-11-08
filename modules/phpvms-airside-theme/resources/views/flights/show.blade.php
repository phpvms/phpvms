@extends('app')
@section('title', $flight->summary->callsign)

@section('content')
    <x-vms::page name="flight-show" label="Flight details">
        <x-ts-link :href="route('frontend.flights.index')" text="Back to flight manifest" icon="phosphor.arrow-left.light" sm navigate class="w-fit" wire:navigate />

        <livewire:flights::flight-briefing :flight-id="$flight->summary->id" />

        <livewire:bids::assignment-drawer />
    </x-vms::page>
@endsection

@section('scripts')
    @airsideVite(['resources/js/islands/route-map.ts'])
@endsection
