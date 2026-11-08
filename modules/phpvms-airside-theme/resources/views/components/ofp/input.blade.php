@props(['name', 'label', 'value' => null, 'readonly' => false])

{{-- A labelled SimBrief option input. Props: name, label, value, readonly (shown, never submitted). --}}
<x-ts-input
    :label="$label"
    id="ofp-{{ $name }}"
    :name="$readonly ? null : $name"
    :value="$value"
    :readonly="$readonly ?: null"
    {{ $attributes }}
/>
