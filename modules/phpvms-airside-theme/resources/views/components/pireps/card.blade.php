@props(['pirep'])

{{--
  One logbook row (PirepCard). Prop: pirep (PirepListItemData). The whole card links to the PIREP.
--}}
@php
    $filed = $pirep->submittedAt ? \Illuminate\Support\Carbon::parse($pirep->submittedAt)->format('d M Y') : null;
@endphp
<a href="{{ route('frontend.pireps.show', ['id' => $pirep->id]) }}" wire:navigate {{ $attributes->class('vms-pirep-card block rounded-md p-3 no-underline sm:p-4') }}>
    <div class="mb-2 flex w-full items-center justify-between gap-2.5">
        <span class="font-mono text-[13px] font-semibold">{{ $pirep->ident }}</span>
        <x-ts-badge :color="\Modules\PhpvmsAirsideTheme\Support\StateColor::tallstack($pirep->stateColor)" light data-state="{{ $pirep->stateColor }}">{{ $pirep->state }}</x-ts-badge>
    </div>

    <div class="flex items-center gap-3">
        <span class="flex min-w-0 flex-col">
            <span class="font-mono text-[13px] font-medium">{{ $pirep->dpt }}</span>
            <span class="truncate text-[11px]">{{ $pirep->dptName ?? '' }}</span>
        </span>
        <span class="shrink-0" aria-hidden="true">→</span>
        <span class="flex min-w-0 flex-col text-right">
            <span class="font-mono text-[13px] font-medium">{{ $pirep->arr }}</span>
            <span class="truncate text-[11px]">{{ $pirep->arrName ?? '' }}</span>
        </span>
    </div>

    <x-vms::ui.stats
        class="mt-2.5 pt-2.5"
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
