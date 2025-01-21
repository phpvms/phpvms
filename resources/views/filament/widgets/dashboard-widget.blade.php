<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex justify-between items-center">
            <img src="{{ public_asset('/assets/img/logo_blue_bg.svg') }}" width="135px" alt="phpvms Logo" />
            <div class="flex items-center">
                <h1 title="{{ $version_full }}">{{ $version }}</h1>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
