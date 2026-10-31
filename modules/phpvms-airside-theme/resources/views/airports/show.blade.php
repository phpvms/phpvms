@extends('app')
@inject('shell', 'App\Features\Shell\ShellData')

@section('title', $airport->full_name)

@section('content')
    <x-vms::page name="airport" label="Airport">
        <x-vms::page.header headline="Airport" :title="$airport->full_name">
            <x-slot:links>
                @if (filled($airport->elevation))
                    <x-ts-badge color="slate" light :text="$airport->elevation.' ft'" />
                @endif
                @if ($airport->hub)
                    <x-ts-badge color="sky" light text="Hub" />
                @endif
            </x-slot:links>
        </x-vms::page.header>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <div class="grid content-start gap-4">
                <livewire:flights::airport-weather lazy :label="__('dashboard.weatherat', ['ICAO' => $airport->icao])" :icao="$airport->icao" />

                @if (filled($airport->notes))
                    <x-ts-card :header="__('airports.notes')">
                        <div class="text-[13px]">{!! $airport->notes !!}</div>
                    </x-ts-card>
                @endif
            </div>

            <x-ts-card :header="__('common.map')">
                @if ($airport->lat !== null && $airport->lon !== null)
                    <div class="min-w-0" data-airside-island="route-map" wire:ignore>
                        <script type="application/json">{!! json_encode([
                            'from'        => ['icao' => $airport->icao, 'lat' => (float) $airport->lat, 'lon' => (float) $airport->lon],
                            'to'          => null,
                            'cruiseLevel' => null,
                            'height'      => 360,
                            'map'         => $shell->map()->toArray(),
                        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
                    </div>
                @else
                    <x-vms::ui.empty icon="map" title="No map" description="This airport has no coordinates." />
                @endif
            </x-ts-card>
        </div>

        @if (count($airport->files) > 0 && Auth::check())
            <x-ts-card :header="trans_choice('common.download', 2)">
                <x-vms::ui.file-list :files="$airport->files" />
            </x-ts-card>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-vms::airports.flights :title="__('flights.inbound')" :flights="$inbound_flights" direction="inbound" />
            <x-vms::airports.flights :title="__('flights.outbound')" :flights="$outbound_flights" direction="outbound" />
        </div>
    </x-vms::page>
@endsection

@section('scripts')
    @airsideVite(['resources/js/islands/route-map.js'])
@endsection
