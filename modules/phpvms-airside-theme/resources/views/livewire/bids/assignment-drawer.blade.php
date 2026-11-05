{{-- The flight assignment (bid) drawer. State comes from App\Features\Bids\Livewire\AssignmentDrawer. --}}
@php
    $payload = in_array($state, ['selecting', 'overview'], true) ? $this->dispatchPayload : null;
    $selection = $payload['selection'] ?? null;
    $policy = $payload['policy'] ?? null;
    $flight = $payload['flight'] ?? null;
    $subfleets = $payload['subfleets'] ?? collect();
    $aircraft = $state === 'selecting' && $subfleetId !== null ? $this->aircraftList : [];
@endphp

<x-vms::ui.drawer name="assignment" title="Flight bid" event="assignment-drawer" subject="flightId" wire:model="open">
    <x-slot:header>
        @if ($flight)
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
                        <dl class="m-0 grid grid-cols-3 gap-3 text-center">
                            <div><dt class="text-[9px] uppercase tracking-wider">Departure</dt><dd class="mt-0.5 text-[11px]">{{ $flight->scheduledDeparture ?? 'Not scheduled' }}</dd></div>
                            <div><dt class="text-[9px] uppercase tracking-wider">Arrival</dt><dd class="mt-0.5 text-[11px]">{{ $flight->scheduledArrival ?? 'Not scheduled' }}</dd></div>
                            <div><dt class="text-[9px] uppercase tracking-wider">Block</dt><dd class="mt-0.5 text-[11px]">{{ $flight->summary->blockTime ?? '—' }}</dd></div>
                        </dl>
                    </section>

                    @if ($failureMessage)
                        <x-ts-alert color="red" light shadowless role="alert" data-failure="{{ $failureType }}">{{ $failureMessage }}</x-ts-alert>
                    @endif

                    <x-vms::bids.aircraft-selection
                        :subfleets="$subfleets"
                        :aircraft="$aircraft"
                        :subfleet-id="$subfleetId"
                        :aircraft-id="$aircraftId"
                        :editing-subfleet="$editingSubfleet"
                        :editing-aircraft="$editingAircraft"
                        :required="$policy->aircraftRequired"
                    />

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
