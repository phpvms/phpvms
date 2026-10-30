@props(['label', 'value' => null, 'mono' => false, 'small' => false, 'accent' => 'ink'])

{{--
  Stat tile (StatTile). Props: label, value (null/'' shows an em dash), mono (data face), small,
  accent (ink|mag|cyan|green|amber: tints the value and adds a 2px top rule). Slot: default replaces value.
--}}
@php
    $tint = match ($accent) {
        'ink' => 'var(--pv-ink)',
        'mag' => 'var(--pv-accent)',
        default => 'var(--pv-'.$accent.')',
    };
    $text = ($value === null || $value === '') ? '—' : $value;
@endphp
<div {{ $attributes->class(['pv-stat relative flex flex-col gap-[5px] overflow-hidden rounded-lg border border-line bg-panel px-4 py-3.5']) }} style="--tile-accent: {{ $tint }}">
    @if ($accent !== 'ink')
        <span class="absolute inset-x-0 top-0 h-0.5" style="background: var(--tile-accent)" aria-hidden="true"></span>
    @endif
    <span class="text-[11px] font-medium text-ink-dim">{{ $label }}</span>
    <span @class([
        'font-bold leading-none tracking-tight tnum',
        'text-[28px]' => ! $mono && ! $small,
        'text-xl' => $small && ! $mono,
        'font-mono !text-[22px] !font-medium' => $mono && ! $small,
        'font-mono !text-xl !font-medium' => $mono && $small,
    ]) style="color: var(--tile-accent)">{{ $slot->isEmpty() ? $text : $slot }}</span>
</div>
