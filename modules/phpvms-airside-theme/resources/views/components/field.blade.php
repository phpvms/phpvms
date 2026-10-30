@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
])

{{-- A labelled form control with its validation error. Extra attributes land on the control. --}}
<div class="grid gap-1.5">
    @if ($label)
        <label for="{{ $attributes->get('id', $name) }}" class="text-[13px] font-medium text-ink">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $attributes->get('id', $name) }}"
        @if ($type !== 'password' && $value !== null) value="{{ $value }}" @endif
        {{ $attributes->except('id') }}
    />

    @error($name)
        <p class="text-xs text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>
