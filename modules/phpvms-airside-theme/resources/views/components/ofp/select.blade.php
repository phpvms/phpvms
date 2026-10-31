@props(['name', 'label', 'options', 'value' => null])

{{-- A labelled native select for the SimBrief options. Props: name, label, options (value => label), value. --}}
<div class="grid gap-1.5">
    <label for="ofp-{{ $name }}" class="text-[13px] font-medium">{{ $label }}</label>
    <select id="ofp-{{ $name }}" name="{{ $name }}" {{ $attributes }}>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
