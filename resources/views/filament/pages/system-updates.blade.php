@php($screen = $this->screen())

<x-filament-panels::page>
    {{-- Keyed per screen so wire:poll is added or dropped with the element, not morphed. --}}
    <div wire:key="system-updates-{{ $screen }}" @if ($screen === 'progress') wire:poll.2s @endif>
        @if ($screen !== 'idle')
            @include('filament.system.updater.progress', ['getLivewire' => fn () => $this])
        @else
            <x-filament::section>
                <div class="space-y-4">
                    @if (! $this->pending())
                        <p>{{ __('installer.updater.nothing_to_update') }}</p>
                    @else
                        <p>{{ __('installer.updater.admin_warning') }}</p>

                        @if (! $this->cronFresh())
                            <p>{{ __('installer.updater.cron_required') }}</p>
                            <pre class="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $this->cronLine() }}</pre>
                        @else
                            <label class="flex items-center gap-2">
                                <x-filament::input.checkbox wire:model.live="confirmed" />
                                <span>{{ __('installer.updater.confirm') }}</span>
                            </label>

                            <x-filament::button wire:click="start" :disabled="! $confirmed">
                                {{ __('installer.updater.start') }}
                            </x-filament::button>
                        @endif
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
