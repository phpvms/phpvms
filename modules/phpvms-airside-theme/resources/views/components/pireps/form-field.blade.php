@props(['label', 'for' => null, 'error' => null, 'hint' => null, 'required' => false])

{{-- A labelled PIREP form row with its validation error. Props: label, for (control id), error (error-bag key), hint, required. Slot: the control. --}}
<div {{ $attributes->class('grid content-start gap-1.5') }}>
    <label @if ($for) for="{{ $for }}" @endif class="text-[13px] font-medium">
        {{ $label }}
        @if ($required)
            <span aria-hidden="true">*</span>
        @endif
        @if ($hint)
            <span class="ml-1 font-normal">{{ $hint }}</span>
        @endif
    </label>

    {{ $slot }}

    @if ($error)
        @error($error)
            <p class="text-xs" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
