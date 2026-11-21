@php($screen = $this->screen())

<x-filament-panels::page>
    {{-- Keyed per screen so wire:poll is added or dropped with the element, not morphed. --}}
    <div wire:key="updater-{{ $screen }}" @if ($screen === 'progress') wire:poll.2s @endif>
        {{ $this->content }}
    </div>
</x-filament-panels::page>
