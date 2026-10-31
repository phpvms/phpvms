@props(['label', 'value' => null, 'mono' => false, 'small' => false, 'accent' => 'ink'])

{{--
  Stat tile: TallStackUI's stats card. Props: label, value (null/'' shows an em dash), mono (data face),
  small, accent (ink|mag|cyan|green|amber: the number colour). Slot: default replaces the value.
--}}
@php
    $tint = match ($accent) {
        'mag' => 'text-primary-600 dark:text-primary-400',
        'cyan' => 'text-cyan-600 dark:text-cyan-400',
        'green' => 'text-green-600 dark:text-green-400',
        'amber' => 'text-amber-600 dark:text-amber-400',
        default => '',
    };
    $text = ($value === null || $value === '') ? '—' : $value;
@endphp
<x-ts-stats :title="$label" {{ $attributes }}>
    <span @class(['font-semibold tabular-nums', $tint, 'font-mono' => $mono, 'text-xl' => $small, 'text-2xl' => ! $small])>{{ $slot->isEmpty() ? $text : $slot }}</span>
</x-ts-stats>
