{{-- The flight assignment (bid) drawer. State comes from App\Features\Bids\Livewire\AssignmentDrawer. --}}
@php
    $payload = in_array($state, ['details', 'selecting', 'overview'], true) ? $this->dispatchPayload : null;
    $selection = $payload['selection'] ?? null;
    $policy = $payload['policy'] ?? null;
    $flight = $payload['flight'] ?? null;
    $subfleets = $payload['subfleets'] ?? collect();
    $aircraft = $state === 'selecting' && $subfleetId !== null ? $this->aircraftList : [];
    $chosenSubfleet = $subfleetId === null ? null : $subfleets->firstWhere('id', $subfleetId);
    $chosenAircraft = collect($aircraft ?? [])->firstWhere('id', $aircraftId);
@endphp

<div
    x-data="{
        opening: false,
        onOpen: null,
        onLoaded: null,
        init() {
            this.onOpen = () => {
                this.opening = true;
                this.$wire.open = true;
            };
            this.onLoaded = () => {
                this.opening = false;
            };

            window.addEventListener('assignment-drawer.open', this.onOpen);
            window.addEventListener('assignment-drawer.loaded', this.onLoaded);

            // Closing from the drawer's own close button, backdrop or Escape only flips `open` in the browser.
            this.$wire.$watch('open', (value) => {
                if (! value && this.$wire.flightId !== null) {
                    this.$wire.close();
                }
            });
        },
        destroy() {
            window.removeEventListener('assignment-drawer.open', this.onOpen);
            window.removeEventListener('assignment-drawer.loaded', this.onLoaded);
        },
    }"
>
    <x-vms::ui.drawer name="assignment" :title="$state === 'details' ? 'Flight details' : 'Flight bid'" wire:model="open">
        <x-slot:header>
            @if ($flight && $state === 'details')
                @php
                    $summary = $flight->summary;
                    // Schedule times are free text: only a bare clock time (0800, 08:00) is read as UTC.
                    $clock = fn (?string $time): ?string => $time === null ? null : (preg_match('/^\d{1,2}:?\d{2}$/', $time) ? $time.' UTC' : $time);
                @endphp
                <x-vms::flights.summary class="vms-detail-summary mb-5" :callsign="$summary->callsign" :airline="$summary->airline?->name" :logo="$summary->airline?->logo">
                    <span class="basis-full text-sm font-semibold tabular-nums">{{ $summary->dpt ?? '—' }} – {{ $summary->arr ?? '—' }}</span>
                    @if ($flight->scheduledDeparture || $flight->scheduledArrival)
                        <span class="basis-full text-xs tabular-nums">{{ $clock($flight->scheduledDeparture) ?? '—' }} – {{ $clock($flight->scheduledArrival) ?? '—' }}</span>
                    @endif
                </x-vms::flights.summary>
            @elseif ($flight)
                <p>FLIGHT ASSIGNMENT</p>
                <x-vms::flights.ident
                    size="lg"
                    :callsign="$flight->summary->callsign"
                    :dpt="$flight->summary->dpt"
                    :arr="$flight->summary->arr"
                    :airline="$flight->summary->airline?->name"
                    :logo="$flight->summary->airline?->logo"
                    :aircraft="$selection?->aircraft ? $selection->aircraft->registration.' · '.$selection->aircraft->icaoType : null"
                />
            @else
                <p>{{ $state === 'details' ? 'FLIGHT DETAILS' : 'FLIGHT ASSIGNMENT' }}</p>
                <span>Loading flight</span>
            @endif
        </x-slot:header>

        <section class="grid gap-4" aria-label="Flight bid">
            <div x-show="opening" x-cloak class="grid gap-2 py-6">
                <div class="inline-flex min-w-0 items-center" role="status" aria-live="polite" aria-label="Loading policy and aircraft"><x-ts-spinner sm text="Loading policy and aircraft" /></div>
                <p class="text-[13px]">The latest availability is checked before you can continue.</p>
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
                        $canBid = $summary->primaryAction === 'bid';
                        $canPlanOfp = $policy->simbriefEnabled && ! $policy->simbriefRequiresBid;
                        $routeMap = $this->routeMap;
                        $legs = [
                            ['key' => 'dpt', 'airport' => $flight->departure, 'icao' => $summary->dpt, 'time' => $flight->scheduledDeparture, 'weather' => $flight->departureWeather, 'label' => 'Departure weather'],
                            ['key' => 'arr', 'airport' => $flight->arrival, 'icao' => $summary->arr, 'time' => $flight->scheduledArrival, 'weather' => $flight->arrivalWeather, 'label' => 'Arrival weather'],
                        ];
                        $pins = array_map(fn (array $leg): array => ['lat' => $leg['airport']?->lat, 'lon' => $leg['airport']?->lon, 'label' => $leg['icao']], $legs);
                        $routes = [[[$flight->departure?->lat, $flight->departure?->lon], [$flight->arrival?->lat, $flight->arrival?->lon]]];
                    @endphp
                    <div class="vms-flight-detail grid gap-5">
                        @if ($routeMap)
                            <div class="vms-detail-map overflow-hidden rounded-xl p-3">
                                <x-map.svg
                                    :map="$routeMap"
                                    pattern="dots"
                                    :pin-radius="7"
                                    :pins="$pins"
                                    :routes="$routes"
                                    fit
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
                                        <x-ts-button type="button" block wire:click="startBid">Add to bids</x-ts-button>
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
                @elseif ($state === 'overview' && $selection)
                    <div class="grid gap-4" aria-live="polite">
                        <dl class="m-0 grid grid-cols-2 gap-4">
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Scheduled departure</dt><dd class="mt-0.5 break-words text-[11px]">{{ $selection->flight->scheduledDeparture ?? 'Not scheduled' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Scheduled arrival</dt><dd class="mt-0.5 break-words text-[11px]">{{ $selection->flight->scheduledArrival ?? 'Not scheduled' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Route</dt><dd class="mt-0.5 break-words text-[11px]">{{ $selection->flight->route ?? 'Not published' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Block</dt><dd class="mt-0.5 break-words text-[11px]">{{ $selection->flight->summary->blockTime ?? '—' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Reservation</dt><dd class="mt-0.5 break-words text-[11px]">{{ $selection->aircraftReserved ? 'Exclusive' : ($selection->aircraft ? 'Preference' : 'Flight only') }}</dd></div>
                            <div class="min-w-0">
                                <dt class="text-[9px] uppercase tracking-wider">Expires</dt>
                                <dd class="mt-0.5 break-words text-[11px]">
                                    @if ($selection->expiresAt)
                                        @php($expires = \Illuminate\Support\Carbon::parse($selection->expiresAt)->utc())
                                        <time datetime="{{ $expires->toIso8601String() }}" x-data x-text="new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date($el.dateTime))">{{ $expires->format('M j, Y, g:i A') }} UTC</time>
                                    @else
                                        Does not expire
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <x-vms::bids.aircraft-card label="Aircraft" :aircraft="$selection->aircraft" />

                        <div class="flex flex-wrap items-center gap-2">
                            <x-slot-outlet name="bids.drawer.overview.actions" :context="['bid' => $selection->bid, 'selection' => $selection]" />
                            @if ($selection->ofpGenerated && $selection->ofpUrl)
                                <x-ts-button :href="$selection->ofpUrl" wire:navigate>View OFP</x-ts-button>
                            @elseif ($selection->policy->simbriefEnabled && $selection->ofpPlanningUrl)
                                <x-ts-button :href="$selection->ofpPlanningUrl" wire:navigate>Generate OFP</x-ts-button>
                            @endif
                        </div>
                    </div>
                @elseif ($state === 'selecting' && $payload)
                    <form class="grid gap-4" wire:submit="submit">
                        <section class="rounded-md px-4 py-3" aria-label="Selected flight">
                            <dl class="m-0 grid grid-cols-3 gap-3">
                                <div><dt class="text-[9px] uppercase tracking-wider">Departure</dt><dd class="mt-0.5 text-[11px]">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd></div>
                                <div><dt class="text-[9px] uppercase tracking-wider">Arrival</dt><dd class="mt-0.5 text-[11px]">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd></div>
                                <div><dt class="text-[9px] uppercase tracking-wider">Block</dt><dd class="mt-0.5 text-[11px]">{{ $flight->summary->blockTime ?? '—' }}</dd></div>
                            </dl>
                        </section>

                        @if ($failureMessage)
                            <x-ts-alert color="red" light shadowless role="alert" data-failure="{{ $failureType }}">{{ $failureMessage }}</x-ts-alert>
                        @endif

                        <fieldset class="m-0 grid min-w-0 gap-3 rounded-lg p-3">
                            <legend class="px-1 text-[13px] font-semibold">Aircraft Assignment</legend>

                            @if (! $chosenSubfleet)
                                <p class="text-xs">Select the subfleet and aircraft to fly with.</p>
                            @endif

                            @if ($editingSubfleet || ! $chosenSubfleet)
                                @if ($chosenSubfleet)
                                    <div class="flex justify-end"><x-ts-button type="button" xs light wire:click="selectSubfleet({{ $chosenSubfleet->id }})">Cancel</x-ts-button></div>
                                @endif
                                <x-vms::bids.picker
                                    label="Eligible subfleet"
                                    action="selectSubfleet"
                                    search-placeholder="Search subfleets"
                                    empty-text="No configured subfleet matches this search."
                                    :items="$subfleets->map(fn ($subfleet) => [
                                        'value' => $subfleet->id,
                                        'title' => $subfleet->displayName,
                                        'sub' => implode(' · ', array_filter([$subfleet->airlineIcao, $subfleet->icaoType])) ?: 'Configured subfleet',
                                        'meta' => $subfleet->availabilityLabel ?? $subfleet->eligibleAircraftCount.' available',
                                        'disabled' => $subfleet->disabled,
                                    ])->all()"
                                />
                            @else
                                <article class="grid min-w-0 gap-0.5 rounded-md px-3 py-2.5" aria-label="Selected subfleet">
                                    <header class="flex items-center justify-between gap-2">
                                        <span>Selected subfleet</span>
                                        <x-ts-button type="button" xs light wire:click="editSubfleet">Edit</x-ts-button>
                                    </header>
                                    <strong class="text-[13px]">{{ $chosenSubfleet->displayName }}</strong>
                                    <span class="text-xs">{{ $chosenSubfleet->eligibleAircraftCount }} eligible aircraft currently available</span>
                                </article>
                            @endif

                            @if ($subfleetId !== null)
                                <div wire:loading wire:target="selectSubfleet,editAircraft,retryAircraft">
                                    <div class="inline-flex min-w-0 items-center" role="status" aria-live="polite" aria-label="Loading current eligible aircraft"><x-ts-spinner sm text="Loading current eligible aircraft" /></div>
                                </div>

                                @if ($aircraft === null)
                                    <div class="grid gap-1.5 rounded-md px-3 py-2.5 text-[13px]" role="alert">
                                        <strong>Aircraft unavailable</strong>
                                        <span>Eligible aircraft could not be loaded. Choose another subfleet or try again.</span>
                                        <div><x-ts-button type="button" xs light wire:click="retryAircraft">Try again</x-ts-button></div>
                                    </div>
                                @elseif ($aircraft === [])
                                    <div class="grid gap-1 rounded-md px-3 py-2.5 text-[13px]" role="status">
                                        <strong>No eligible aircraft</strong>
                                        @if ($policy->aircraftRequired)
                                            <span>No active, parked aircraft meets the current server policy for this flight.</span>
                                        @else
                                            <span>You can still create a flight-only bid.</span>
                                        @endif
                                    </div>
                                @else
                                    @if ($editingAircraft || ! $chosenAircraft)
                                        @if ($chosenAircraft)
                                            <div class="flex justify-end"><x-ts-button type="button" xs light wire:click="selectAircraft({{ $chosenAircraft->id }})">Cancel</x-ts-button></div>
                                        @endif
                                        <x-vms::bids.picker
                                            label="Eligible aircraft"
                                            action="selectAircraft"
                                            search-placeholder="Search registrations or types"
                                            empty-text="No eligible aircraft matches this search."
                                            :items="array_map(fn ($item) => [
                                                'value' => $item->id,
                                                'title' => $item->registration,
                                                'sub' => $item->icaoType.' · '.$item->subfleetName,
                                                'meta' => $item->airport?->icao ?? 'Airport unavailable',
                                            ], $aircraft)"
                                        />
                                    @else
                                        <x-vms::bids.aircraft-card label="Selected aircraft" :aircraft="$chosenAircraft">
                                            <x-slot:action>
                                                <x-ts-button type="button" xs light wire:click="editAircraft">Edit</x-ts-button>
                                            </x-slot:action>
                                        </x-vms::bids.aircraft-card>
                                    @endif
                                @endif
                            @endif
                        </fieldset>

                        <p class="text-xs">
                            {{ $policy->aircraftRequired
                                ? 'The selected aircraft is reserved exclusively after server confirmation.'
                                : 'Aircraft is optional. A selection is stored as a preference and is not reserved exclusively.' }}
                        </p>

                        <div class="flex justify-end gap-2">
                            <x-ts-button type="button" light wire:click="close" wire:loading.attr="disabled" wire:target="submit">Cancel</x-ts-button>
                            <x-ts-button submit wire:loading.attr="disabled" wire:target="submit" :disabled="$policy->aircraftRequired && $aircraftId === null">
                                <span wire:loading.remove wire:target="submit">Select</span>
                                <span wire:loading wire:target="submit">Confirming bid</span>
                            </x-ts-button>
                        </div>
                        <p class="m-0 text-xs" aria-live="polite" wire:loading wire:target="submit">Server is revalidating flight and aircraft availability.</p>
                    </form>
                @endif
            </div>
        </section>
    </x-vms::ui.drawer>
</div>
