{{-- The flight assignment (bid) drawer. State comes from App\Features\Bids\Livewire\AssignmentDrawer. --}}
@php
    $payload = in_array($state, ['selecting', 'overview'], true) ? $this->dispatchPayload : null;
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
    <x-airside::drawer name="assignment" title="Flight bid" wire:model="open">
        <x-slot:header>
            <p class="micro">FLIGHT ASSIGNMENT</p>
            @if ($flight)
                <x-airside::flight-ident
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
                <x-airside::loading text="Loading policy and aircraft" />
                <p class="text-[13px]">The latest availability is checked before you can continue.</p>
            </div>

            <div x-show="! opening" class="grid gap-4">
                @if ($state === 'error')
                    <div class="grid gap-2 py-4" role="alert">
                        <strong>Flight data unavailable</strong>
                        <p class="text-[13px]">{{ $failureMessage }}</p>
                        <div><x-ts-button type="button" sm wire:click="load">Try again</x-ts-button></div>
                    </div>
                @elseif ($state === 'overview' && $selection)
                    <div class="grid gap-4" aria-live="polite">
                        <dl class="m-0 grid grid-cols-2 gap-4">
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Scheduled departure</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $selection->flight->scheduledDeparture ?? 'Not scheduled' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Scheduled arrival</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $selection->flight->scheduledArrival ?? 'Not scheduled' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Route</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $selection->flight->route ?? 'Not published' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Block</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $selection->flight->summary->blockTime ?? '—' }}</dd></div>
                            <div class="min-w-0"><dt class="text-[9px] uppercase tracking-wider">Reservation</dt><dd class="mt-0.5 break-words font-mono text-[11px]">{{ $selection->aircraftReserved ? 'Exclusive' : ($selection->aircraft ? 'Preference' : 'Flight only') }}</dd></div>
                            <div class="min-w-0">
                                <dt class="text-[9px] uppercase tracking-wider">Expires</dt>
                                <dd class="mt-0.5 break-words font-mono text-[11px]">
                                    @if ($selection->expiresAt)
                                        @php($expires = \Illuminate\Support\Carbon::parse($selection->expiresAt)->utc())
                                        <time datetime="{{ $expires->toIso8601String() }}" x-data x-text="new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date($el.dateTime))">{{ $expires->format('M j, Y, g:i A') }} UTC</time>
                                    @else
                                        Does not expire
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <x-airside::bids-aircraft-card label="Aircraft" :aircraft="$selection->aircraft" />

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
                                <div><dt class="text-[9px] uppercase tracking-wider">Departure</dt><dd class="mt-0.5 font-mono text-[11px]">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd></div>
                                <div><dt class="text-[9px] uppercase tracking-wider">Arrival</dt><dd class="mt-0.5 font-mono text-[11px]">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd></div>
                                <div><dt class="text-[9px] uppercase tracking-wider">Block</dt><dd class="mt-0.5 font-mono text-[11px]">{{ $flight->summary->blockTime ?? '—' }}</dd></div>
                            </dl>
                        </section>

                        @if ($failureMessage)
                            <x-airside::alert kind="error" data-failure="{{ $failureType }}">{{ $failureMessage }}</x-airside::alert>
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
                                <x-airside::bids-picker
                                    label="Eligible subfleet"
                                    action="selectSubfleet"
                                    search-placeholder="Search subfleets"
                                    empty-text="No configured subfleet matches this search."
                                    :selected="$subfleetId"
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
                                        <span class="micro">Selected subfleet</span>
                                        <x-ts-button type="button" xs light wire:click="editSubfleet">Edit</x-ts-button>
                                    </header>
                                    <strong class="text-[13px]">{{ $chosenSubfleet->displayName }}</strong>
                                    <span class="text-xs">{{ $chosenSubfleet->eligibleAircraftCount }} eligible aircraft currently available</span>
                                </article>
                            @endif

                            @if ($subfleetId !== null)
                                <div wire:loading wire:target="selectSubfleet,editAircraft,retryAircraft">
                                    <x-airside::loading text="Loading current eligible aircraft" />
                                </div>

                                @if ($aircraft === null)
                                    <div class="grid gap-1.5 rounded-md border border-bad/40 bg-bad/10 px-3 py-2.5 text-[13px]" role="alert">
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
                                        <x-airside::bids-picker
                                            label="Eligible aircraft"
                                            action="selectAircraft"
                                            search-placeholder="Search registrations or types"
                                            empty-text="No eligible aircraft matches this search."
                                            :selected="$aircraftId"
                                            :items="array_map(fn ($item) => [
                                                'value' => $item->id,
                                                'title' => $item->registration,
                                                'sub' => $item->icaoType.' · '.$item->subfleetName,
                                                'meta' => $item->airport?->icao ?? 'Airport unavailable',
                                            ], $aircraft)"
                                        />
                                    @else
                                        <x-airside::bids-aircraft-card label="Selected aircraft" :aircraft="$chosenAircraft">
                                            <x-slot:action>
                                                <x-ts-button type="button" xs light wire:click="editAircraft">Edit</x-ts-button>
                                            </x-slot:action>
                                        </x-airside::bids-aircraft-card>
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
    </x-airside::drawer>
</div>
