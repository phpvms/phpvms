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
            <p>{{ $state === 'details' ? 'FLIGHT DETAILS' : 'FLIGHT ASSIGNMENT' }}</p>
            @if ($flight && $state === 'details')
                {{-- The route tile and title replace the ident here. --}}
            @elseif ($flight)
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
                    @php($summary = $flight->summary)
                    @php($depWest = $flight->departure?->lon === null || $flight->arrival?->lon === null || $flight->departure->lon <= $flight->arrival->lon)
                    @php($leftPoint = $depWest ? $summary->dpt : $summary->arr)
                    @php($rightPoint = $depWest ? $summary->arr : $summary->dpt)
                    @php($canBid = $summary->primaryAction === 'bid')
                    @php($canPlanOfp = $policy->simbriefEnabled && ! $policy->simbriefRequiresBid)
                    <div class="vms-flight-detail grid gap-4">
                        <div class="vms-route-tile relative h-44 overflow-hidden rounded-2xl" aria-label="{{ $summary->dpt }} to {{ $summary->arr }}">
                            <p class="vms-route-tile-label absolute top-3 left-4 m-0 text-[10px] font-semibold tracking-wider uppercase">{{ $summary->callsign }} · Great circle</p>
                            <div class="vms-route-tile-arc absolute top-[22%] right-[15%] bottom-[30%] left-[15%] rounded-t-[50%_100%] border-2 border-b-0 border-dashed">
                                <span class="vms-route-tile-plane absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 {{ $depWest ? 'rotate-90' : '-rotate-90' }}"><x-ts-icon name="phosphor.airplane.light" class="block size-4" /></span>
                            </div>
                            <div class="vms-route-tile-stop absolute top-[70%] left-[15%] flex -translate-x-1/2 flex-col items-center">
                                <span class="vms-route-tile-dot -mt-1.25 block size-2.5 rounded-full"></span>
                                <span class="mt-1 text-[11px] tabular-nums font-semibold">{{ $leftPoint ?? '—' }}</span>
                            </div>
                            <div class="vms-route-tile-stop absolute top-[70%] right-[15%] flex translate-x-1/2 flex-col items-center">
                                <span class="vms-route-tile-dot -mt-1.25 block size-2.5 rounded-full"></span>
                                <span class="mt-1 text-[11px] tabular-nums font-semibold">{{ $rightPoint ?? '—' }}</span>
                            </div>
                        </div>

                        <div class="flex items-start justify-between gap-3">
                            <h3 class="vms-detail-title m-0 min-w-0 text-xl leading-snug break-words"><b class="font-bold">{{ $flight->departure?->name ?? $summary->dpt ?? '—' }}</b> <span class="font-light">to</span> <b class="font-bold">{{ $flight->arrival?->name ?? $summary->arr ?? '—' }}</b></h3>
                            @if ($summary->type)
                                <span class="vms-flight-pill shrink-0 px-2 py-0.5 text-[11px] font-medium">{{ $summary->type }}</span>
                            @endif
                        </div>

                        <dl class="vms-detail-facts m-0 grid grid-cols-2 gap-x-4 gap-y-3.5">
                            <div class="min-w-0"><dt class="text-xs">Departure</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ $flight->scheduledDeparture ? $flight->scheduledDeparture.' UTC · ' : '' }}{{ $summary->dpt ?? '—' }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs">Arrival</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ $flight->scheduledArrival ? $flight->scheduledArrival.' UTC · ' : '' }}{{ $summary->arr ?? '—' }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs">Block time</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ $summary->blockTime ?? '—' }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs">Distance</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ $summary->distanceNm === null ? '—' : number_format($summary->distanceNm).' nm' }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs">Cruise level</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ $flight->cruiseLevel === null ? '—' : 'FL'.$flight->cruiseLevel }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs">Route code</dt><dd class="mt-0.5 break-words text-sm font-semibold">{{ $summary->routeCode ?? '—' }}</dd></div>
                        </dl>

                        <div class="vms-detail-actions grid gap-2.5">
                            @if ($canBid || $canPlanOfp)
                                <div class="grid auto-cols-fr grid-flow-col gap-2.5">
                                    @if ($canBid)
                                        <x-ts-button type="button" block wire:click="startBid">Bid on flight</x-ts-button>
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
