{{-- The flight schedule. State comes from App\Features\Flights\Livewire\FlightManifest. --}}
@php
    $results = $this->results;
    $flights = $results['flights'];
    $policy = $results['policy'];
    $pager = $results['page'];
    $options = $this->options;
    $active = $this->activeFilters;
    $mono = fn (?string $value) => $value ?? '—';
@endphp

<x-vms::page name="flights" label="Flights" class="min-w-0">
    <x-vms::page.header class="vms-flights-header">
        <x-slot:title>Find a <span>flight.</span></x-slot:title>
        <x-slot:links><span class="vms-flights-count">{{ count($active) }} active</span></x-slot:links>
    </x-vms::page.header>

    <x-vms::flights.filters :options="$options" :active="$active" :search-error="$searchError" />

    @if ($flights)
        <ul
            class="vms-flight-list m-0 grid list-none gap-2.5 p-0"
            x-data="{ selected: null }"
            x-on:assignment-drawer.open.window="selected = String($event.detail.flightId)"
            x-on:assignment-drawer.closed.window="selected = null"
            x-on:flight-overview.open.window="selected = String($event.detail.flightId)"
            x-on:flight-overview.closed.window="selected = null"
        >
            @foreach ($flights as $flight)
                @php($flightId = $flight->id)
                <li
                    wire:key="row-{{ $flightId }}"
                    data-availability="{{ $flight->primaryAction === 'unavailable' ? 'unavailable' : 'available' }}"
                    x-bind:aria-current="selected === '{{ $flightId }}' ? 'true' : null"
                    class="vms-flight-card grid min-w-0 cursor-pointer items-center gap-x-5 gap-y-3 px-4.5 py-4 md:grid-cols-[auto_minmax(0,1fr)_10rem]"
                    x-on:click="$dispatch('flight-overview.open', { flightId: '{{ $flightId }}' })"
                >
                    <x-vms::flights.summary :callsign="$flight->callsign" :airline="$flight->airline?->name" :logo="$flight->airline?->logo" class="md:w-60">
                        <x-slot:button>
                            <button type="button" class="block cursor-pointer rounded-sm text-left font-bold focus-visible:outline-2 focus-visible:outline-offset-2" aria-label="Show {{ $flight->callsign }} details" x-on:click.stop="$dispatch('flight-overview.open', { flightId: '{{ $flightId }}' })">{{ $flight->callsign }}</button>
                        </x-slot:button>
                        @if ($flight->type)
                            <span class="vms-flight-pill px-2 py-0.5 text-[11px] font-medium">{{ $flight->type }}</span>
                        @endif
                        @if ($flight->routeCode)
                            <span class="vms-flight-pill px-2 py-0.5 text-[11px] font-medium">{{ $flight->routeCode }}</span>
                        @endif
                        @if ($flight->primaryAction === 'overview')
                            <span class="vms-flight-pill vms-flight-pill--bid px-2 py-0.5 text-[11px] font-semibold">On your bid</span>
                        @elseif ($flight->primaryAction === 'unavailable')
                            <span class="vms-flight-pill vms-flight-pill--off px-2 py-0.5 text-[11px] font-semibold">Unavailable</span>
                        @endif
                    </x-vms::flights.summary>

                    <div class="vms-flight-route grid min-w-0 grid-cols-[auto_minmax(60px,1fr)_auto] items-center gap-4">
                        <div class="vms-flight-stop">
                            <b class="block text-2xl leading-none font-extrabold tracking-tight">{{ $mono($flight->dpt) }}</b>
                            <small class="mt-1.5 block text-xs tabular-nums">{{ $mono($flight->scheduledDeparture) }}</small>
                        </div>
                        <div class="vms-flight-leg relative grid items-center">
                            <i class="vms-flight-line block h-px"></i>
                            <div class="absolute inset-0 flex items-center justify-center gap-1.5">
                                @if ($flight->blockTime)
                                    <span class="vms-flight-leg-pill px-2 py-0.5 text-[11px] font-medium tabular-nums">{{ $flight->blockTime }}</span>
                                @endif
                                <span class="vms-flight-plane px-1"><x-ts-icon name="phosphor.airplane.light" class="block size-4 rotate-90" /></span>
                                @if ($flight->distanceNm !== null)
                                    <span class="vms-flight-leg-pill px-2 py-0.5 text-[11px] font-medium tabular-nums">{{ number_format($flight->distanceNm) }} nm</span>
                                @endif
                            </div>
                        </div>
                        <div class="vms-flight-stop text-right">
                            <b class="block text-2xl leading-none font-extrabold tracking-tight">{{ $mono($flight->arr) }}</b>
                            <small class="mt-1.5 block text-xs tabular-nums">{{ $mono($flight->scheduledArrival) }}</small>
                        </div>
                    </div>

                    <div class="vms-flight-buttons grid gap-2">
                        @if ($flight->primaryAction === 'unavailable')
                            <x-ts-button type="button" sm block color="gray" light disabled class="text-center whitespace-normal" x-on:click.stop>{{ $flight->availabilityReason ?? 'Unavailable' }}</x-ts-button>
                        @else
                            <x-ts-button type="button" sm block :light="$flight->primaryAction === 'overview'" x-on:click.stop="$dispatch('assignment-drawer.open', { flightId: '{{ $flightId }}' })" x-on:keydown.stop>{{ $flight->primaryAction === 'overview' ? 'View bid' : 'Bid on flight' }}</x-ts-button>
                        @endif
                        <x-ts-button :href="route('frontend.flights.show', $flightId)" wire:navigate sm block color="gray" light class="vms-flight-details" x-on:click.stop>Details</x-ts-button>
                    </div>

                    <div class="vms-flight-row-actions col-span-full flex items-center gap-2 not-has-[*]:hidden" x-on:click.stop x-on:keydown.stop>
                        <x-slot-outlet name="flights.row.actions" :context="['flight' => $flight, 'policy' => $policy]" />
                    </div>

                    @if ($flight->availabilityReason)
                        <p class="vms-flight-reason col-span-full px-3 py-2 text-[12.5px] font-medium">{{ $flight->availabilityReason }}</p>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($pager['last'] > 1)
            <nav class="vms-flight-pager grid grid-cols-[110px_minmax(0,1fr)_110px] items-center gap-3 p-2 text-center max-[500px]:grid-cols-2" aria-label="Flight results pages">
                @if ($pager['current'] > 1)
                    <x-ts-button type="button" sm round light wire:click="previousPage">Previous</x-ts-button>
                @else
                    <span class="px-3.5 py-2 text-left text-[13px] font-semibold" aria-disabled="true">Previous</span>
                @endif
                <p class="m-0 text-xs tabular-nums max-[500px]:order-first max-[500px]:col-span-2">Page {{ $pager['current'] }} of {{ $pager['last'] }} · {{ $pager['total'] }} flights</p>
                @if ($pager['current'] < $pager['last'])
                    <x-ts-button type="button" sm round light wire:click="nextPage" class="justify-self-end">Next</x-ts-button>
                @else
                    <span class="px-3.5 py-2 text-right text-[13px] font-semibold" aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
    @else
        <x-vms::ui.empty class="vms-flights-empty" icon="paper-airplane" title="No flights found" description="Change or clear the active filters to see another part of the schedule." aria-live="polite" />
    @endif
</x-vms::page>
