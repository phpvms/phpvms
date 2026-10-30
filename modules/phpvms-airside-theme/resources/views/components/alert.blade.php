@props(['kind' => 'info'])

@php
    $tone = match ($kind) {
        'success' => 'border-ok/40 bg-ok/10 text-ok',
        'error' => 'border-bad/40 bg-bad/10 text-bad',
        default => 'border-line bg-panel-inset text-ink',
    };
@endphp

<div {{ $attributes->class(['rounded-md border px-3 py-2 text-[13px]', $tone]) }} role="{{ $kind === 'error' ? 'alert' : 'status' }}">
    {{ $slot }}
</div>
