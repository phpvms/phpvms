@props([
    'subfleets',
    'aircraft' => [],
    'subfleetId' => null,
    'aircraftId' => null,
    'editingSubfleet' => true,
    'editingAircraft' => true,
    'required' => false,
    'heading' => true,
])

{{--
  Aircraft assignment for a bid (skylight's AircraftSelection): pick a subfleet, then an aircraft, each from a
  searchable x-vms::ui.select-menu; a choice collapses to a card with Edit. Props: subfleets (EligibleSubfleetData
  collection), aircraft (list of EligibleAircraftData for the chosen subfleet; null when they failed to load),
  subfleetId, aircraftId, editingSubfleet, editingAircraft, required (aircraft required by policy), heading (false
  when the surrounding UI already titles the step: the legend stays for screen readers only).
  It calls the enclosing Livewire component's selectSubfleet, editSubfleet, selectAircraft, editAircraft and
  retryAircraft (see App\Features\Bids\Livewire\AssignmentDrawer).
--}}
@php
    $chosenSubfleet = $subfleetId === null ? null : $subfleets->firstWhere('id', $subfleetId);
    $chosenAircraft = collect($aircraft ?? [])->firstWhere('id', $aircraftId);
    $line = fn (array $parts): string => implode(' · ', array_filter($parts));
@endphp

<fieldset {{ $attributes->class('vms-aircraft-selection m-0 grid min-w-0 gap-3 p-0') }}>
    <legend @class(['mb-1 p-0 text-[15px] font-semibold', 'sr-only' => ! $heading])>Select Aircraft</legend>

    @if ($heading && ! $chosenSubfleet)
        <p class="text-xs">Select the subfleet and aircraft to fly with.</p>
    @endif

    @if ($editingSubfleet || ! $chosenSubfleet)
        <div class="grid gap-1.5">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold tracking-wider uppercase">Subfleet</span>
                @if ($chosenSubfleet)
                    <x-ts-button type="button" xs light wire:click="selectSubfleet({{ $chosenSubfleet->id }})">Cancel</x-ts-button>
                @endif
            </div>
            <x-vms::ui.select-menu
                wire:key="subfleet-select-{{ $subfleetId ?? 'none' }}"
                placeholder="Select an eligible subfleet"
                search-placeholder="Search subfleets"
                empty-text="No configured subfleet matches this search."
                :disabled="$subfleets->isEmpty()"
                :items="$subfleets->map(fn ($subfleet) => [
                    'value' => $subfleet->id,
                    'title' => $subfleet->displayName,
                    'sub' => $line([$subfleet->airlineIcao, $subfleet->icaoType]) ?: 'Configured subfleet',
                    'meta' => $subfleet->availabilityLabel ?? $subfleet->eligibleAircraftCount.' available',
                    'disabled' => $subfleet->disabled,
                ])->values()->all()"
                x-on:select="$wire.selectSubfleet($event.detail.value)"
            />
        </div>
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
                @if ($required)
                    <span>No active, parked aircraft meets the current server policy for this flight.</span>
                @else
                    <span>You can still create a flight-only bid.</span>
                @endif
            </div>
        @elseif ($editingAircraft || ! $chosenAircraft)
            <div class="grid gap-1.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold tracking-wider uppercase">Eligible aircraft</span>
                    @if ($chosenAircraft)
                        <x-ts-button type="button" xs light wire:click="selectAircraft({{ $chosenAircraft->id }})">Cancel</x-ts-button>
                    @endif
                </div>
                <x-vms::ui.select-menu
                    wire:key="aircraft-select-{{ $subfleetId }}"
                    placeholder="Select an eligible aircraft"
                    search-placeholder="Search registrations or types"
                    empty-text="No eligible aircraft matches this search."
                    :items="array_map(fn ($item) => [
                        'value' => $item->id,
                        'title' => $item->registration,
                        'sub' => $item->icaoType.' · '.$item->subfleetName,
                        'meta' => $item->airport?->icao ?? 'Airport unavailable',
                    ], $aircraft)"
                    x-on:select="$wire.selectAircraft($event.detail.value)"
                />
            </div>
        @else
            <x-vms::bids.aircraft-card label="Selected aircraft" :aircraft="$chosenAircraft">
                <x-slot:action>
                    <x-ts-button type="button" xs light wire:click="editAircraft">Edit</x-ts-button>
                </x-slot:action>
            </x-vms::bids.aircraft-card>
        @endif
    @endif
</fieldset>
