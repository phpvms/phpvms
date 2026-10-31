@props(['name', 'label', 'value' => null, 'readonly' => false])

{{-- A labelled SimBrief option input. Props: name, label, value, readonly (shown, never submitted). --}}
<div class="grid gap-1.5">
    <label for="ofp-{{ $name }}" class="text-[13px] font-medium">{{ $label }}</label>
    <input
        id="ofp-{{ $name }}"
        type="text"
        @if (! $readonly) name="{{ $name }}" @endif
        value="{{ $value }}"
        @readonly($readonly)
        {{ $attributes->class(['opacity-70' => $readonly]) }}
    />
</div>
