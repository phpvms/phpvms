{{-- PIREP summary: ident, aircraft, pilot, state, route panel and the stat strip (PirepSummary). Variable: $pirep (PirepData). --}}
@php
    $filed = $pirep->submittedAt ? \Illuminate\Support\Carbon::parse($pirep->submittedAt)->format('d M Y, H:i') : '—';
    $stats = [
        ['label' => 'Flight Time', 'value' => $pirep->flightTime],
        ['label' => 'Distance', 'value' => $pirep->distance],
        ['label' => 'Score', 'value' => $pirep->score],
        ['label' => 'Landing Rate', 'value' => $pirep->landingRate !== null ? round($pirep->landingRate).' fpm' : null],
        ['label' => 'Fuel Used', 'value' => $pirep->fuelUsed],
        ['label' => 'Cruise', 'value' => $pirep->cruise],
    ];
@endphp
<div class="pv-pirep-summary grid gap-3">
    <header class="flex items-start justify-between gap-3">
        <div>
            <h1 class="m-0 font-mono text-xl font-semibold text-accent">{{ $pirep->ident }}</h1>
            <div class="mt-0.5 text-[13px] text-ink">{{ $pirep->aircraft ?? '—' }}</div>
            <div class="mt-0.5 text-[11px] text-ink-dim">
                @if ($pirep->pilotName)
                    {{ $pirep->pilotName }}@if ($pirep->pilotRank) · {{ $pirep->pilotRank }}@endif ·
                @endif
                Filed {{ $filed }}
            </div>
        </div>
        <x-airside::state-badge :state-color="$pirep->stateColor" :label="$pirep->state" class="shrink-0" />
    </header>

    <div class="flex items-center gap-4 rounded-md border border-line bg-panel px-3.5 py-3">
        <div class="flex flex-col">
            <span class="font-mono text-[15px] font-semibold">{{ $pirep->dpt }}</span>
            <span class="text-[11px] text-ink-dim">{{ $pirep->dptName ?? '' }}</span>
        </div>
        <span class="text-ink-faint" aria-hidden="true">→</span>
        <div class="ml-auto flex flex-col text-right">
            <span class="font-mono text-[15px] font-semibold">{{ $pirep->arr }}</span>
            <span class="text-[11px] text-ink-dim">{{ $pirep->arrName ?? '' }}</span>
        </div>
    </div>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(96px,1fr))] gap-px overflow-hidden rounded-md border border-line bg-line">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-[3px] bg-panel px-3 py-2.5">
                <span class="text-[10px] text-ink-faint uppercase">{{ $stat['label'] }}</span>
                <span class="text-[13px] tnum">{{ ($stat['value'] ?? '') === '' ? '—' : $stat['value'] }}</span>
            </div>
        @endforeach
    </div>
</div>
