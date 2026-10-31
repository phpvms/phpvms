@extends('app')
@inject('shell', 'App\Features\Shell\ShellData')

@section('title', 'Live map')

@section('content')
    @php($map = $shell->map()->toArray())

    <x-vms::page name="live-map" label="Live map">
        {{-- The live-map island (resources/js/islands/live-map.js) fills the list, the count, the map and the overlay. --}}
        <div data-airside-island="live-map" class="grid gap-6">
            <script type="application/json">{!! json_encode(['updateIntervalSeconds' => $updateIntervalSeconds, 'initialCenter' => $initialCenter, 'map' => $map], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>

            <x-vms::page.header headline="Operations" title="Live flights" description="Flights currently in the air, plotted on the globe.">
                <x-slot:links>
                    <x-ts-badge color="slate" light><span data-live-count>0 flights</span></x-ts-badge>
                </x-slot:links>
            </x-vms::page.header>

            <div class="grid grid-cols-1 gap-4 min-[721px]:grid-cols-[minmax(16rem,20rem)_minmax(0,1fr)]">
                <aside data-live-list class="max-h-[40vh] min-w-0 overflow-y-auto rounded-lg p-3 min-[721px]:max-h-[70vh]" aria-label="Live flights"></aside>

                <div class="relative min-w-0 overflow-hidden rounded-lg">
                    <div data-live-map class="h-[70vh] min-h-[22rem]"></div>
                    <div data-live-overlay class="pointer-events-none absolute top-3 left-3 z-10 max-w-[min(320px,calc(100%-24px))]"></div>
                </div>
            </div>

            <x-vms::livemap.templates />
        </div>
    </x-vms::page>
@endsection

@section('scripts')
    @airsideVite(['resources/js/islands/live-map.js'])
@endsection
