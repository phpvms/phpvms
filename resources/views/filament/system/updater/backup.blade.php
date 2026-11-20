@php($livewire = $getLivewire())

<div class="space-y-4">
    <p>{{ __('installer.updater.warning') }}</p>

    @if (! $livewire->cronFresh())
        <p>{{ __('installer.updater.cron_required') }}</p>
        <pre class="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $livewire->cronLine() }}</pre>
    @else
        <label class="flex items-center gap-2">
            <x-filament::input.checkbox wire:model.live="confirmed" />
            <span>{{ __('installer.updater.confirm') }}</span>
        </label>

        <x-filament::button wire:click="start" :disabled="! $livewire->confirmed || ! $livewire->canStart()">
            {{ __('installer.updater.start') }}
        </x-filament::button>
    @endif
</div>
