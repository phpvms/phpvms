{{-- Aircraft selection on the OFP planning page. State comes from App\Features\Ofp\Livewire\AircraftSelection. --}}
<fieldset class="grid gap-3 rounded-md border border-line-strong bg-panel-inset p-3" wire:key="ofp-aircraft-selection">
    <legend class="micro px-1">Aircraft Assignment</legend>
    <p class="text-[13px] text-ink-dim">Select the subfleet and aircraft to fly with.</p>

    @if ($failure)
        <x-airside::alert kind="error">{{ $failure }}</x-airside::alert>
    @endif

    <div class="grid gap-2" role="radiogroup" aria-label="Eligible subfleet">
        <span class="text-[13px] font-medium text-ink">Eligible subfleet</span>
        @forelse ($this->subfleets as $subfleet)
            <button
                type="button"
                role="radio"
                aria-checked="{{ $subfleetId === $subfleet->id ? 'true' : 'false' }}"
                wire:key="subfleet-{{ $subfleet->id }}"
                wire:click="selectSubfleet({{ $subfleet->id }})"
                @disabled($subfleet->disabled)
                @class([
                    'flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-left text-[13px]',
                    'border-accent bg-accent-soft' => $subfleetId === $subfleet->id,
                    'border-line bg-panel hover:bg-hover' => $subfleetId !== $subfleet->id,
                    'opacity-60' => $subfleet->disabled,
                ])
            >
                <span class="grid min-w-0">
                    <strong class="truncate">{{ $subfleet->displayName }}</strong>
                    <span class="truncate text-ink-dim">{{ collect([$subfleet->airlineIcao, $subfleet->icaoType])->filter()->implode(' · ') ?: 'Configured subfleet' }}</span>
                </span>
                <span class="shrink-0 text-xs text-ink-dim">{{ $subfleet->availabilityLabel ?? $subfleet->eligibleAircraftCount.' available' }}</span>
            </button>
        @empty
            <p class="text-[13px] text-ink-dim">No configured subfleet is available for this flight.</p>
        @endforelse
    </div>

    @if ($subfleetId !== null)
        <div class="grid gap-2" aria-label="Eligible aircraft">
            <span class="text-[13px] font-medium text-ink">Aircraft</span>
            @forelse ($this->aircraft as $aircraft)
                <button
                    type="button"
                    wire:key="aircraft-{{ $aircraft->id }}"
                    wire:click="chooseAircraft({{ $aircraft->id }})"
                    wire:loading.attr="disabled"
                    class="grid gap-0.5 rounded-md border border-line bg-panel px-3 py-2 text-left text-[13px] hover:bg-hover"
                >
                    <strong class="font-mono">{{ $aircraft->registration }} · {{ $aircraft->icaoType }}</strong>
                    <span class="text-ink-dim">{{ $aircraft->subfleetName }} · {{ $aircraft->airport?->icao }}</span>
                </button>
            @empty
                <p class="text-[13px] text-ink-dim">No eligible aircraft in this subfleet. Choose another subfleet.</p>
            @endforelse
        </div>
    @endif

    <div wire:loading wire:target="chooseAircraft">
        <x-airside::loading text="Saving aircraft assignment" />
    </div>
</fieldset>
