@props(['label', 'aircraft' => null])

{{-- An aircraft summary card (AircraftCard). Props: label, aircraft (an EligibleAircraftData or null: "Aircraft not selected"). Slot: action (e.g. an Edit button). --}}
<article {{ $attributes->class('grid min-w-0 gap-0.5 rounded-md border border-line-strong bg-panel-inset px-3 py-2.5') }} aria-label="{{ $label }}">
    <header class="flex items-center justify-between gap-2">
        <span class="micro">{{ $label }}</span>
        @isset($action)
            {{ $action }}
        @endisset
    </header>
    @if ($aircraft)
        <strong class="text-[13px] text-ink">{{ $aircraft->registration }} · {{ $aircraft->icaoType }}</strong>
        <span class="text-xs text-ink-dim">{{ $aircraft->subfleetName }} · {{ $aircraft->airport?->icao ?? '' }}</span>
    @else
        <strong class="text-[13px] text-ink">Aircraft not selected</strong>
    @endif
</article>
