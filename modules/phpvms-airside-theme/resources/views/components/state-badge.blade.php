@props(['stateColor' => 'gray', 'label' => null])

{{--
  PIREP/state badge (stateBadgeColor.ts). Props: stateColor (success|warning|danger|info|gray, as the DTOs
  emit; anything else is gray), label (text; the slot is used when label is absent).
--}}
@php
    $color = match ($stateColor) {
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'info' => 'sky',
        default => 'slate',
    };
@endphp
<x-ts-badge :color="$color" light :attributes="$attributes->class('pv-state-badge')" data-state="{{ $stateColor }}">
    {{ $label ?? $slot }}
</x-ts-badge>
