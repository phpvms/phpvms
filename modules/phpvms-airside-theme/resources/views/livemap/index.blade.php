@inject('shell', 'App\Features\Shell\ShellData')

{{--
  The live map at /live. Signed-in pilots get their shell with the sidebar folded to its rail;
  guests get the top bar layout. Data: updateIntervalSeconds and initialCenter from
  LiveMapController; the map config comes from the shell.
--}}
@php
    $guest = $shell->user() === null;
    $topbar = $guest || theme_option('layout') === 'topbar';
@endphp

<x-vms::layouts.app title="Live map" :topbar="$guest ? true : null">
    <x-vms::livemap
        :update-interval-seconds="$updateIntervalSeconds"
        :initial-center="$initialCenter"
        :map="$shell->map()->toArray()"
        :rail="! $topbar"
        :class="$topbar ? 'min-h-[32rem] h-[calc(100dvh-13.5rem)]' : 'min-h-[32rem] h-[calc(100dvh-8.5rem)]'"
    />

    <x-slot:scripts>
        @airsideVite(['resources/js/islands/live-map.js'])
    </x-slot:scripts>
</x-vms::layouts.app>
