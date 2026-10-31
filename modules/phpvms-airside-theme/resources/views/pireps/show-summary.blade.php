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
<div class="vms-pirep-summary grid gap-3">
    <header class="flex items-start justify-between gap-3">
        <div>
            <h1 class="m-0 font-mono text-xl font-semibold">{{ $pirep->ident }}</h1>
            <div class="mt-0.5 text-[13px]">{{ $pirep->aircraft ?? '—' }}</div>
            <div class="mt-0.5 text-[11px]">
                @if ($pirep->pilotName)
                    {{ $pirep->pilotName }}@if ($pirep->pilotRank) · {{ $pirep->pilotRank }}@endif ·
                @endif
                Filed {{ $filed }}
            </div>
        </div>
        <x-ts-badge :color="\Modules\PhpvmsAirsideTheme\Support\StateColor::tallstack($pirep->stateColor)" light data-state="{{ $pirep->stateColor }}" class="shrink-0">{{ $pirep->state }}</x-ts-badge>
    </header>

    <div class="flex items-center gap-4 rounded-md px-3.5 py-3">
        <div class="flex flex-col">
            <span class="font-mono text-[15px] font-semibold">{{ $pirep->dpt }}</span>
            <span class="text-[11px]">{{ $pirep->dptName ?? '' }}</span>
        </div>
        <span aria-hidden="true">→</span>
        <div class="ml-auto flex flex-col text-right">
            <span class="font-mono text-[15px] font-semibold">{{ $pirep->arr }}</span>
            <span class="text-[11px]">{{ $pirep->arrName ?? '' }}</span>
        </div>
    </div>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(96px,1fr))] gap-px overflow-hidden rounded-md">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-[3px] px-3 py-2.5">
                <span class="text-[10px] uppercase">{{ $stat['label'] }}</span>
                <span class="text-[13px]">{{ ($stat['value'] ?? '') === '' ? '—' : $stat['value'] }}</span>
            </div>
        @endforeach
    </div>
</div>
