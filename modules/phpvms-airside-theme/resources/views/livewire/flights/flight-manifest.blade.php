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

<div class="pv-flights-page grid min-w-0 gap-4" aria-label="Flight schedule">
    <section class="rounded-xl p-5" aria-labelledby="flight-filter-heading" x-data="{ open: {{ count($active) > 0 ? 'true' : 'false' }} }">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p>SCHEDULE MANIFEST</p>
                <h1 id="flight-filter-heading" class="mt-1 text-2xl font-semibold">Find a flight</h1>
            </div>
            <span class="rounded-full px-2.5 py-1 font-mono text-[10px]">{{ count($active) }} active</span>
        </div>

        @if ($active)
            <div class="mt-3 flex flex-wrap gap-1.5" aria-label="Active filters">
                @foreach ($active as $chip)
                    <span wire:key="chip-{{ $loop->index }}" class="rounded-full px-2.5 py-1 font-mono text-[10px]">{{ $chip }}</span>
                @endforeach
            </div>
        @endif

        @if ($searchError)
            <x-airside::alert kind="error" class="mt-3">{{ $searchError }}</x-airside::alert>
        @endif

        <div class="mt-4">
            <button type="button" class="cursor-pointer text-[13px] font-semibold" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-controls="flight-filter-form">Filters</button>
        </div>

        <form id="flight-filter-form" wire:submit="apply" x-show="open" x-cloak class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Airline</span>
                <select wire:model="airlineId">
                    <option value="">All airlines</option>
                    @foreach ($options['airlines'] as $value => $label)
                        @continue($value === '')
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Flight type</span>
                <select wire:model="flightType">
                    <option value="">All types</option>
                    @foreach ($options['flightTypes'] as $value => $label)
                        @continue($value === '')
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Flight number</span>
                <input type="text" wire:model="flightNumber" autocomplete="off" placeholder="104" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Route code</span>
                <input type="text" wire:model="routeCode" autocomplete="off" placeholder="A" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Departure</span>
                <input type="text" wire:model="depIcao" autocomplete="off" maxlength="8" placeholder="KDFW" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Arrival</span>
                <input type="text" wire:model="arrIcao" autocomplete="off" maxlength="8" placeholder="KORD" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Subfleet</span>
                <select wire:model="subfleetId">
                    <option value="">All subfleets</option>
                    @foreach ($options['subfleets'] as $value => $label)
                        @continue($value === '')
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Type rating</span>
                <select wire:model="typeRatingId">
                    <option value="">All ratings</option>
                    @foreach ($options['typeRatings'] as $rating)
                        <option value="{{ $rating['id'] }}">{{ $rating['type'] }} · {{ $rating['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">ICAO type</span>
                <select wire:model="icaoType">
                    <option value="">All aircraft</option>
                    @foreach ($options['icaoTypes'] as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Free text</span>
                <input type="text" wire:model="search" autocomplete="off" placeholder="Airport, route, callsign" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Minimum distance</span>
                <input type="text" wire:model="distanceGreaterThan" inputmode="numeric" placeholder="0" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Maximum distance</span>
                <input type="text" wire:model="distanceLessThan" inputmode="numeric" placeholder="Any" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Minimum time</span>
                <input type="text" wire:model="timeGreaterThan" inputmode="numeric" placeholder="Minutes" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Maximum time</span>
                <input type="text" wire:model="timeLessThan" inputmode="numeric" placeholder="Minutes" />
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Sort by</span>
                <select wire:model="orderBy">
                    <option value="">Default</option>
                    <option value="dpt_time">Departure time</option>
                    <option value="arr_time">Arrival time</option>
                    <option value="distance">Distance</option>
                    <option value="flight_number">Flight number</option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Direction</span>
                <select wire:model="sortedBy">
                    <option value="">Default</option>
                    <option value="asc">Ascending</option>
                    <option value="desc">Descending</option>
                </select>
            </label>
            <label class="grid min-w-0 gap-1.5"><span class="text-[11px] font-semibold">Page size</span>
                <input type="text" wire:model="limit" inputmode="numeric" placeholder="25" />
            </label>

            <div class="flex justify-end gap-2 sm:col-span-2 lg:col-span-4">
                <x-ts-button submit sm wire:loading.attr="disabled" wire:target="apply,clear">
                    <span wire:loading.remove wire:target="apply">Apply filters</span>
                    <span wire:loading wire:target="apply">Updating results</span>
                </x-ts-button>
                <x-ts-button type="button" sm light wire:click="clear" wire:loading.attr="disabled" wire:target="apply,clear">Clear</x-ts-button>
            </div>
        </form>
    </section>

    @if ($flights)
        {{-- A new result set gets a fresh Alpine scope, so the first row is selected again. --}}
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.75fr)]" wire:key="manifest-{{ md5(implode(',', array_map(fn ($flight) => $flight->id, $flights))) }}" x-data="{ selected: @js($flights[0]->id) }">
            <section class="min-w-0 rounded-xl" aria-labelledby="manifest-heading">
                <header class="flex items-end justify-between gap-4 px-5 py-4">
                    <div>
                        <p>AVAILABLE OPERATIONS</p>
                        <h2 id="manifest-heading" class="mt-1 text-xl font-semibold">Flight manifest</h2>
                    </div>
                    <span class="font-mono text-[10px]">{{ count($flights) }} shown</span>
                </header>

                <div role="list">
                    @foreach ($flights as $flight)
                        @php($flightId = $flight->id)
                        <article
                            wire:key="row-{{ $flightId }}"
                            role="listitem"
                            tabindex="0"
                            class="grid min-w-0 cursor-pointer gap-3 px-5 py-3.5 focus-visible:outline-2 focus-visible:-outline-offset-2 md:grid-cols-[minmax(135px,0.65fr)_minmax(220px,1fr)_minmax(120px,auto)] md:items-center md:gap-4"
                            x-bind:aria-current="selected === @js($flightId) ? 'true' : null"
                            aria-label="Show {{ $flight->callsign }} operational context"
                            x-on:click="selected = @js($flightId)"
                            x-on:keydown.enter.prevent="selected = @js($flightId)"
                            x-on:keydown.space.prevent="selected = @js($flightId)"
                        >
                            <div class="grid min-w-0 gap-1">
                                <span class="font-mono text-sm font-bold">{{ $flight->callsign }}</span>
                                <span class="flex items-center gap-2"><strong>{{ $mono($flight->dpt) }}</strong><span>→</span><strong>{{ $mono($flight->arr) }}</strong></span>
                            </div>

                            <dl class="m-0 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Depart</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $mono($flight->scheduledDeparture) }}</dd></div>
                                <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Arrive</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $mono($flight->scheduledArrival) }}</dd></div>
                                <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Block</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $mono($flight->blockTime) }}</dd></div>
                                <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Distance</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $flight->distanceNm === null ? '—' : $flight->distanceNm.' NM' }}</dd></div>
                            </dl>

                            <div class="flex min-w-0 flex-wrap items-center gap-2 md:justify-end">
                                @if ($flight->primaryAction === 'overview')
                                    <x-airside::state-badge stateColor="info" label="On bid" />
                                @elseif ($flight->availability === 'available')
                                    <x-airside::state-badge stateColor="success" label="Available" />
                                @else
                                    <x-airside::state-badge stateColor="gray" label="Unavailable" />
                                @endif

                                <a href="{{ route('frontend.flights.show', $flightId) }}" wire:navigate class="text-[11px] font-semibold hover:underline" x-on:click.stop>Details</a>

                                @if ($flight->primaryAction !== 'unavailable')
                                    <x-ts-button type="button" sm :light="$flight->primaryAction === 'overview'" x-on:click.stop="$dispatch('assignment-drawer.open', { flightId: '{{ $flightId }}' })">{{ $flight->primaryAction === 'overview' ? 'Overview' : 'Bid' }}</x-ts-button>
                                @else
                                    <x-ts-button type="button" sm disabled x-on:click.stop>Unavailable</x-ts-button>
                                @endif

                                <div class="contents" x-on:click.stop x-on:keydown.stop>
                                    <x-slot-outlet name="flights.row.actions" :context="['flight' => $flight, 'policy' => $policy]" />
                                </div>
                            </div>

                            @if ($flight->availabilityReason)
                                <p class="text-[11px] md:col-span-3">{{ $flight->availabilityReason }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <aside class="grid min-w-0 content-start xl:sticky xl:top-4 xl:self-start" aria-labelledby="selected-flight-heading">
                @foreach ($flights as $flight)
                    <div wire:key="selected-{{ $flight->id }}" x-show="selected === @js($flight->id)" @if (! $loop->first) x-cloak @endif class="rounded-xl p-5">
                        <p class="mb-3" @if ($loop->first) id="selected-flight-heading" @endif>SELECTED FLIGHT</p>
                        <x-airside::flight-ident
                            size="lg"
                            :callsign="$flight->callsign"
                            :dpt="$flight->dpt"
                            :arr="$flight->arr"
                            :airline="$flight->airline?->name"
                            :logo="$flight->airline?->logo"
                            :href="route('frontend.flights.show', $flight->id)"
                        />
                        <dl class="m-0 mt-5 grid grid-cols-2 gap-4 py-4">
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Scheduled departure</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Scheduled arrival</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Route code</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $mono($flight->routeCode) }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Flight type</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $mono($flight->type) }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Distance</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $flight->distanceNm === null ? '—' : $flight->distanceNm.' NM' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Block</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $mono($flight->blockTime) }}</dd></div>
                        </dl>
                        <p class="my-4 text-xs"><strong>OFP:</strong> Not generated</p>
                        <div class="grid gap-2.5">
                            @if ($flight->primaryAction !== 'unavailable')
                                <x-ts-button type="button" block x-on:click="$dispatch('assignment-drawer.open', { flightId: '{{ $flight->id }}' })">{{ $flight->primaryAction === 'overview' ? 'View Bid' : 'Bid on flight' }}</x-ts-button>
                            @endif
                        </div>
                        <x-slot-outlet name="flights.detail.actions" :context="['flight' => $flight, 'policy' => $policy]" />
                    </div>
                @endforeach
            </aside>
        </div>

        @if ($pager['last'] > 1)
            <nav class="grid grid-cols-[90px_minmax(0,1fr)_90px] items-center gap-3 rounded-md px-4 py-3 text-center max-[500px]:grid-cols-2" aria-label="Flight results pages">
                @if ($pager['current'] > 1)
                    <button type="button" wire:click="previousPage" class="cursor-pointer text-left text-xs font-semibold">Previous</button>
                @else
                    <span class="text-left text-xs font-semibold" aria-disabled="true">Previous</span>
                @endif
                <p class="m-0 font-mono text-[10px] max-[500px]:order-first max-[500px]:col-span-2">Page {{ $pager['current'] }} of {{ $pager['last'] }} · {{ $pager['total'] }} flights</p>
                @if ($pager['current'] < $pager['last'])
                    <button type="button" wire:click="nextPage" class="cursor-pointer text-right text-xs font-semibold">Next</button>
                @else
                    <span class="text-right text-xs font-semibold" aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
    @else
        <section class="rounded-xl px-6 py-10 text-center" aria-live="polite">
            <h2 class="text-xl font-semibold">No flights found</h2>
            <p class="mt-2">Change or clear the active filters to see another part of the schedule.</p>
        </section>
    @endif
</div>
