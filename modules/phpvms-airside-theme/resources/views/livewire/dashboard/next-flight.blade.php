{{-- The next flight widget. Data comes from App\Features\Dashboard\Livewire\NextFlight. --}}
@php
    $bid = $this->bid;
    $flight = $bid?->flight;
@endphp

@if ($flight)
    <div class="vms-next-flight grid gap-5">
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,16rem)] sm:items-start">
            <div class="min-w-0">
                <h4 class="vms-next-flight-route m-0 text-3xl font-semibold tracking-tight">
                    {{ $flight->dpt ?? '—' }}
                    <span class="vms-next-flight-arrow" aria-hidden="true">→</span>
                    {{ $flight->arr ?? '—' }}
                </h4>
                <div class="vms-next-flight-pills mt-3 flex flex-wrap items-center gap-1.5">
                    <span class="vms-flight-pill px-2 py-0.5 text-[11px] font-medium">{{ $flight->callsign }}</span>
                    @if (filled($flight->type))
                        <span class="vms-flight-pill px-2 py-0.5 text-[11px] font-medium">{{ $flight->type }}</span>
                    @endif
                    @if (filled($flight->routeCode))
                        <span class="vms-flight-pill px-2 py-0.5 text-[11px] font-medium">{{ $flight->routeCode }}</span>
                    @endif
                </div>
            </div>

            @if (filled($flight->dpt))
                <div class="vms-next-flight-metar min-w-0">
                    <livewire:flights::airport-weather lazy :key="'next-flight-weather-'.$flight->dpt" label="Departure weather" :icao="$flight->dpt" />
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <dl class="vms-next-flight-stats m-0 flex flex-wrap items-center gap-y-3">
                <div class="vms-next-flight-stat flex items-center gap-2 pr-5">
                    <x-ts-icon name="phosphor.clock.light" class="size-5 shrink-0" />
                    <div>
                        <dt class="text-xs text-dark-400">Block time</dt>
                        <dd class="m-0 text-sm font-semibold tabular-nums">{{ $flight->blockTime ?? '—' }}</dd>
                    </div>
                </div>
                <div class="vms-next-flight-stat flex items-center gap-2 border-l border-dark-400/25 px-5">
                    <x-ts-icon name="phosphor.path.light" class="size-5 shrink-0" />
                    <div>
                        <dt class="text-xs text-dark-400">Distance</dt>
                        <dd class="m-0 text-sm font-semibold tabular-nums">{{ $flight->distanceNm === null ? '—' : number_format($flight->distanceNm).' nm' }}</dd>
                    </div>
                </div>
                <div class="vms-next-flight-stat flex items-center gap-2 border-l border-dark-400/25 pl-5">
                    <x-ts-icon :name="$bid->ofpUrl ? 'phosphor.check-circle.light' : 'phosphor.minus-circle.light'" class="size-5 shrink-0" />
                    <div>
                        <dt class="text-xs text-dark-400">Briefing</dt>
                        <dd class="m-0 text-sm font-semibold">{{ $bid->ofpUrl ? __('ui.ofp_ready') : __('ui.ofp_not_planned') }}</dd>
                    </div>
                </div>
            </dl>

            <div class="vms-next-flight-actions flex flex-wrap items-center gap-2">
                <x-ts-button type="button" light x-on:click="$dispatch('assignment-drawer.open', { flightId: '{{ $flight->id }}' })">{{ __('ui.start_flight_planning') }}</x-ts-button>
                <x-ts-button :href="route('frontend.flights.show', $flight->id)" wire:navigate flat>Details</x-ts-button>
            </div>
        </div>
    </div>
@else
    @php
        $airport = $this->location;
        $map = $this->mapOptions;
    @endphp
    <div class="vms-next-flight-empty">
        <x-vms::location-card
            :icao="$airport?->icao"
            :country="$airport?->country"
            :lat="$airport?->lat"
            :lon="$airport?->lon"
            :scope="$map['scope']"
            :variant="$map['variant']"
            :size="$map['size']"
        >
            @php($lastFlight = $this->lastFlight)
            @if ($lastFlight)
                <x-slot:aside>
                    <div class="vms-last-flight p-3">
                        <p class="m-0 text-xs text-dark-400">{{ __('ui.last_flight_title') }}</p>
                        <p class="m-0 mt-1 text-sm font-semibold tabular-nums">
                            {{ $lastFlight['dpt'] }}
                            <span class="vms-last-flight-arrow" aria-hidden="true">→</span>
                            {{ $lastFlight['arr'] }}
                        </p>
                        <p class="m-0 mt-1 text-xs tabular-nums text-dark-400">{{ $lastFlight['time'] }} · {{ $lastFlight['distance'] }}</p>
                    </div>
                </x-slot:aside>
            @endif

            <x-slot:details>
                <x-slot-outlet name="dashboard.ready.details" :context="['airport' => $airport, 'user' => auth()->user(), 'pirep' => auth()->user()?->last_pirep]" />
            </x-slot:details>

            <x-ts-button :href="route('frontend.flights.index')" wire:navigate>{{ __('ui.find_a_flight') }}</x-ts-button>
            <x-slot-outlet name="dashboard.ready.actions" :context="['airport' => $airport, 'user' => auth()->user()]" />
        </x-vms::location-card>
    </div>
@endif
