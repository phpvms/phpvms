@props(['kind' => 'info'])

{{-- Alert: TallStackUI's alert in its light style. Prop: kind (info|success|error). Slot: the message. --}}
@php
    $color = match ($kind) {
        'success' => 'green',
        'error' => 'red',
        default => 'primary',
    };
@endphp
<x-ts-alert :color="$color" light shadowless {{ $attributes->merge(['role' => $kind === 'error' ? 'alert' : 'status']) }}>
    {{ $slot }}
</x-ts-alert>
