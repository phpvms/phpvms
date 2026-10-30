@props(['tour', 'windowed' => false])

{{--
  A tour's legs as a timeline (TourLegTimeline): numbered markers that become checks once a leg is flown, the
  current leg of a run in progress marked "Up next". Props: tour (TourListItemData), windowed (a long tour shows
  a window of 6 legs around the current leg; the others carry x-show="expanded" for the card's toggle).
--}}
@php
    $legs = $tour->legs;
    $total = count($legs);
    $collapsible = $windowed && $total > 8;
    $current = $tour->status === 'in_progress'
        ? max(0, collect($legs)->search(fn ($leg): bool => $leg->flightId === $tour->activeLegFlightId) ?: 0)
        : 0;
    $start = $collapsible ? min(max($current - 1, 0), $total - 6) : 0;
@endphp
<ol {{ $attributes->class('pv-tour-legs grid') }}>
    @foreach ($legs as $index => $leg)
        @php
            $isCurrent = $tour->status === 'in_progress' && $leg->flightId === $tour->activeLegFlightId;
            $inWindow = ! $collapsible || ($index >= $start && $index < $start + 6);
            $state = $leg->flown ? 'flown' : ($isCurrent ? 'next' : null);
        @endphp
        <li
            wire:key="leg-{{ $tour->id }}-{{ $leg->flightId }}"
            class="pv-tour-leg flex items-baseline gap-3 py-1.5"
            data-leg-window="{{ $inWindow ? 'in' : 'out' }}"
            @if (! $inWindow) x-show="expanded" x-cloak @endif
        >
            <span @class([
                'grid size-6 shrink-0 place-items-center rounded-full border text-[11px] font-semibold',
                'border-ok bg-ok/10 text-ok' => $leg->flown,
                'border-accent bg-accent-soft text-accent' => $isCurrent,
                'border-line-strong text-ink-dim' => ! $leg->flown && ! $isCurrent,
            ])>
                @if ($leg->flown)
                    <x-airside::icon name="check" class="size-3.5" />
                @else
                    {{ $leg->routeLeg }}
                @endif
            </span>
            <span class="flex flex-wrap items-baseline gap-x-3">
                <span class="font-mono font-semibold">{{ $leg->ident }}</span>
                <span class="font-mono">{{ $leg->dpt }} <span aria-hidden="true">→</span><span class="sr-only">to</span> {{ $leg->arr }}</span>
                @if ($state)
                    <span @class(['text-[11px] font-bold uppercase', 'text-accent' => $state === 'next', 'text-ok' => $state === 'flown']) data-state="{{ $state }}">{{ $state === 'next' ? 'Up next' : 'Flown' }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ol>
