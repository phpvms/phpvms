@props(['duty'])

{{-- Duty state as a badge. DutyStateData::color is a TallStackUI colour name or `neutral`. --}}
@php($color = ($duty?->color ?? 'neutral') === 'neutral' ? 'gray' : $duty->color)

<span class="shrink-0 whitespace-nowrap"><x-ts-badge :text="$duty?->label ?? 'Off duty'" :color="$color" light round /></span>
