@props(['name', 'label', 'options', 'value' => null])

{{-- A labelled native select for the SimBrief options. Props: name, label, options (value => label), value. --}}
@php
    $selectOptions = collect($options)
        ->map(fn (string $optionLabel, int|string $optionValue): array => ['value' => (string) $optionValue, 'label' => $optionLabel])
        ->values()
        ->all();
@endphp
<x-ts-select.native
    :label="$label"
    id="ofp-{{ $name }}"
    :name="$name"
    :value="(string) $value"
    :options="$selectOptions"
    select="label:label|value:value"
    {{ $attributes }}
/>
