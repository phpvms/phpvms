@props(['pirep'])

{{--
  One logbook row (PirepCard). Prop: pirep (PirepListItemData). The whole card links to the PIREP.
--}}
@php
    $filed = $pirep->submittedAt ? \Illuminate\Support\Carbon::parse($pirep->submittedAt)->format('d M Y') : null;
@endphp
<a href="{{ route('frontend.pireps.show', ['id' => $pirep->id]) }}" wire:navigate {{ $attributes->class('pv-pirep-card block rounded-md border border-line bg-panel p-3 no-underline hover:bg-panel-inset sm:p-4') }}>
    <div class="mb-2 flex w-full items-center justify-between gap-2.5">
        <span class="font-mono text-[13px] font-semibold text-accent">{{ $pirep->ident }}</span>
        <x-airside::state-badge :state-color="$pirep->stateColor" :label="$pirep->state" />
    </div>

    <div class="flex items-center gap-3">
        <span class="flex min-w-0 flex-col">
            <span class="font-mono text-[13px] font-medium text-ink">{{ $pirep->dpt }}</span>
            <span class="truncate text-[11px] text-ink-dim">{{ $pirep->dptName ?? '' }}</span>
        </span>
        <span class="shrink-0 text-ink-faint" aria-hidden="true">→</span>
        <span class="flex min-w-0 flex-col text-right">
            <span class="font-mono text-[13px] font-medium text-ink">{{ $pirep->arr }}</span>
            <span class="truncate text-[11px] text-ink-dim">{{ $pirep->arrName ?? '' }}</span>
        </span>
    </div>

    <x-airside::stats
        class="mt-2.5 border-t border-line pt-2.5"
        :columns="5"
        :items="[
            ['label' => 'Aircraft', 'value' => $pirep->aircraft],
            ['label' => 'Time', 'value' => $pirep->flightTime],
            ['label' => 'Distance', 'value' => $pirep->distance],
            ['label' => 'Score', 'value' => $pirep->score],
            ['label' => 'Filed', 'value' => $filed],
        ]"
    />
</a>
