{{-- The flight overview drawer. State comes from App\Features\Flights\Livewire\FlightOverviewDrawer. --}}
@php
    $payload = $state === 'details' ? $this->dispatchPayload : null;
    $policy = $payload['policy'] ?? null;
    $flight = $payload['flight'] ?? null;
@endphp

<x-vms::ui.drawer name="flight-overview" title="Flight details" event="flight-overview" subject="flightId" wire:model="open">
    <x-slot:header>
        @if ($flight)
            @php
                $summary = $flight->summary;
                // Schedule times are free text: only a bare clock time (0800, 08:00) is read as UTC.
                $clock = fn (?string $time): ?string => $time === null ? null : (preg_match('/^\d{1,2}:?\d{2}$/', $time) ? $time.' UTC' : $time);
            @endphp
            <div class="vms-detail-summary mb-5 grid gap-2">
                <x-vms::flights.ident
                    size="lg"
                    :callsign="$summary->callsign"
                    :dpt="$summary->dpt"
                    :arr="$summary->arr"
                    :airline="$summary->airline?->name"
                    :logo="$summary->airline?->logo"
                />
            </div>
        @else
            <p>FLIGHT DETAILS</p>
            <span>Loading flight</span>
        @endif
    </x-slot:header>

    <section class="grid gap-4" aria-label="Flight details">
        <div x-show="opening" x-cloak class="grid gap-2 py-6">
            <div class="inline-flex min-w-0 items-center" role="status" aria-live="polite" aria-label="Loading flight"><x-ts-spinner sm text="Loading flight" /></div>
        </div>

        <div x-show="! opening" class="grid gap-4">
            @if ($state === 'error')
                <div class="grid gap-2 py-4" role="alert">
                    <strong>Flight data unavailable</strong>
                    <p class="text-[13px]">{{ $failureMessage }}</p>
                    <div><x-ts-button type="button" sm wire:click="load">Try again</x-ts-button></div>
                </div>
            @elseif ($state === 'details' && $flight)
                @php
                    $summary = $flight->summary;
                    $hasBid = $summary->primaryAction === 'overview';
                    $canBid = $hasBid || $summary->primaryAction === 'bid';
                    $canPlanOfp = $policy->simbriefEnabled && ! $policy->simbriefRequiresBid;
                    $routeMap = $this->routeMap;
                    $legs = [
                        ['key' => 'dpt', 'airport' => $flight->departure, 'icao' => $summary->dpt, 'time' => $flight->scheduledDeparture, 'weather' => $flight->departureWeather, 'label' => 'Departure weather'],
                        ['key' => 'arr', 'airport' => $flight->arrival, 'icao' => $summary->arr, 'time' => $flight->scheduledArrival, 'weather' => $flight->arrivalWeather, 'label' => 'Arrival weather'],
                    ];
                @endphp
                <div class="vms-flight-detail grid gap-5">
                    @if ($routeMap)
                        <div class="vms-detail-map overflow-hidden rounded-xl p-3">
                            <x-map.svg
                                :map-view="$routeMap"
                                pattern="dots"
                                :pin-radius="7"
                                class="block aspect-[4/3] w-full"
                            />
                        </div>
                    @endif

                    @foreach ($legs as $leg)
                        @if ($leg['key'] === 'arr' && $flight->route)
                            <div class="vms-detail-route grid gap-1">
                                <h4 class="m-0 text-xs font-semibold">Route</h4>
                                <p class="m-0 break-words font-mono text-xs leading-relaxed">{{ $flight->route }}</p>
                            </div>
                        @endif
                        <div class="vms-detail-leg grid gap-2">
                            <div class="min-w-0">
                                <h3 class="m-0 text-base leading-snug font-bold break-words">{{ $leg['airport']?->name ?? $leg['icao'] ?? '—' }}</h3>
                                <p class="m-0 text-sm tabular-nums">{{ $leg['time'] ? $clock($leg['time']).' · ' : '' }}{{ $leg['icao'] ?? '—' }}</p>
                            </div>
                            @if ($leg['weather'])
                                <livewire:flights::airport-weather lazy :key="'detail-weather-'.$leg['key'].'-'.$leg['weather']->icao" :label="$leg['label']" :icao="$leg['weather']->icao" />
                            @endif
                        </div>
                    @endforeach

                    <div class="vms-detail-actions grid gap-2.5 pt-2">
                        @if ($canBid || $canPlanOfp)
                            <div class="grid auto-cols-fr grid-flow-col gap-2.5">
                                @if ($canBid)
                                    <x-ts-button type="button" block :light="$hasBid" wire:click="startBid">{{ $hasBid ? 'View bid' : 'Add to bids' }}</x-ts-button>
                                @endif
                                @if ($canPlanOfp)
                                    <x-ts-button :href="$flight->ofpPlanningUrl" wire:navigate block light>Generate OFP</x-ts-button>
                                @endif
                            </div>
                        @endif
                        @if ($summary->primaryAction === 'unavailable' && $summary->availabilityReason)
                            <p class="vms-flight-reason px-3 py-2 text-[12.5px] font-medium" role="status">{{ $summary->availabilityReason }}</p>
                        @endif
                        <x-slot-outlet name="flights.detail.actions" :context="['flight' => $summary, 'policy' => $policy]" />
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-vms::ui.drawer>
