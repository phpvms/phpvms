{{--
    Degraded-but-working banner, stated where the counts are read rather than in
    a toast that has already gone: the operator needs it while deciding whether
    to trust the list.
--}}
@php
    $catalog = $this->catalogState();
    $host = parse_url((string) config('addon-manager.registry_url'), PHP_URL_HOST) ?: __('addon-manager::addons.not_synced');
@endphp

<div class="notice">
    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::ArrowsClockwiseLight" />

    <span class="notice__text">
        <strong>
            @if ($catalog['synced_at'])
                {{ __('addon-manager::addons.synced_when', ['when' => \Illuminate\Support\Carbon::parse($catalog['synced_at'])->diffForHumans()]) }}
            @else
                {{ __('addon-manager::addons.not_synced') }}
            @endif
        </strong>
        <span>{{ __('addon-manager::addons.showing_cached_catalog') }} — {{ $host }}</span>
    </span>

    <x-filament::button color="gray" size="sm" wire:click="refreshCatalog">
        {{ __('addon-manager::addons.check_updates') }}
    </x-filament::button>
</div>
