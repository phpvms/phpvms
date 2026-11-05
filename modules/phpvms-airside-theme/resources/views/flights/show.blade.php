@extends('app')
@section('title', $flight->summary->callsign)

@section('content')
    @php
        $summary = $flight->summary;
    @endphp

    <x-vms::page name="flight-show" label="Flight details">
        <x-ts-link :href="route('frontend.flights.index')" text="Back to flight manifest" icon="phosphor.arrow-left.light" sm navigate class="w-fit" wire:navigate />

        <article class="grid min-w-0 gap-4">
            <header class="flex flex-wrap items-center justify-between gap-5 rounded-xl p-6">
                <div>
                    <p class="mb-3">FLIGHT DISPATCH</p>
                    <x-vms::flights.ident
                        size="lg"
                        :callsign="$summary->callsign"
                        :dpt="$summary->dpt"
                        :arr="$summary->arr"
                        :airline="$summary->airline?->name"
                        :logo="$summary->airline?->logo"
                    />
                </div>
                <livewire:flights::flight-actions :flight-id="$summary->id" />
            </header>

            <dl class="m-0 grid grid-cols-2 gap-y-4 rounded-xl px-5 py-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    'Scheduled departure' => $flight->scheduledDeparture ?? 'Not scheduled',
                    'Scheduled arrival' => $flight->scheduledArrival ?? 'Not scheduled',
                    'Distance' => $summary->distanceNm === null ? '—' : $summary->distanceNm.' NM',
                    'Block time' => $summary->blockTime ?? '—',
                    'Route code' => $summary->routeCode ?? '—',
                    'Cruise' => $flight->cruiseLevel === null ? '—' : 'FL'.$flight->cruiseLevel,
                ] as $label => $value)
                    <div class="min-w-0 px-3.5 first:pl-0">
                        <dt class="text-[9px] uppercase tracking-wider">{{ $label }}</dt>
                        <dd class="mt-1 break-words font-mono text-[11px]">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            <section class="rounded-xl p-5" aria-labelledby="route-map-heading">
                <div class="mb-3.5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p>ROUTE</p>
                        <h2 id="route-map-heading" class="mt-1 text-lg font-semibold">Operational route</h2>
                    </div>
                    <p class="max-w-full font-mono text-[10px] sm:max-w-[50%] sm:text-right">{{ $flight->route ?? 'Route not published' }}</p>
                </div>
                <x-vms::flights.route-map :flight="$flight" />
            </section>

            <section class="grid gap-3.5 md:grid-cols-3" aria-label="Airport weather">
                <livewire:flights::airport-weather lazy label="Departure weather" :icao="$flight->departureWeather?->icao" />
                <livewire:flights::airport-weather lazy label="Arrival weather" :icao="$flight->arrivalWeather?->icao" />
                <livewire:flights::airport-weather lazy label="Alternate weather" :icao="$flight->alternateWeather?->icao" empty-label="No alternate airport" />
            </section>

            <section class="grid items-center gap-3.5 rounded-xl px-5 py-4 sm:grid-cols-[auto_auto_minmax(0,1fr)]" aria-label="Operational flight plan state">
                <p>OFP</p>
                <strong>Not generated</strong>
                <span class="text-xs">Generate the OFP with SimBrief after bid confirmation.</span>
            </section>
        </article>

        <livewire:bids::assignment-drawer />
    </x-vms::page>
@endsection

@section('scripts')
    @airsideVite(['resources/js/islands/route-map.ts'])
@endsection
