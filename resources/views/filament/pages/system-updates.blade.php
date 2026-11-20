<x-filament-panels::page>
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
</x-filament-panels::page>
