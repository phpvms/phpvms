@php
    $livewire = $getLivewire();
    // Polls re-render this; only read the scan once the run is done.
    $done = $livewire->screen() === 'done';
    $theme = $done ? $livewire->themeScan() : null;
@endphp

@if ($done)
<x-filament::section>
    <div class="space-y-4">
        <p>{{ __('installer.updater.done') }}</p>

        @if ($theme && $theme['found'] !== [])
            <x-filament::callout
                color="warning"
                :description="__('installer.updater.theme_deprecated', ['theme' => $theme['theme'], 'items' => implode(', ', $theme['found'])])"
            />
        @endif

        {{-- Full load: wire:navigate into another panel leaves its Alpine components half-initialised. --}}
        <x-filament::button tag="a" :href="$livewire->adminUrl()" :spa-mode="false">
            {{ __('installer.updater.go_to_admin') }}
        </x-filament::button>
    </div>
</x-filament::section>
@endif
