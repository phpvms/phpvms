@php
    /**
     * @var \Filament\Panel[] $panels
     * @var \Filament\Panel   $current
     */
    $panelLabel = static function (\Filament\Panel $panel): string {
        return $panel->getId() === 'admin'
            ? __('common.administration')
            : \Illuminate\Support\Str::headline($panel->getId());
    };
@endphp

@if (count($panels) > 1)
    <x-filament::dropdown placement="bottom-start" teleport>
        <x-slot name="trigger">
            <button
                type="button"
                class="fi-topbar-item-btn flex items-center gap-x-1.5 ml-4 rounded-lg px-2 py-1.5 text-sm font-medium text-gray-700 outline-none transition duration-75 hover:bg-gray-100 focus-visible:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
            >
                <x-filament::icon icon="heroicon-o-squares-2x2" class="h-5 w-5" />
                <span>{{ $panelLabel($current) }}</span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($panels as $panel)
                @php($isCurrent = $panel->getId() === $current->getId())
                <x-filament::dropdown.list.item
                    :href="$panel->getUrl() ?? url($panel->getPath())"
                    tag="a"
                    :icon="$isCurrent ? 'heroicon-m-check' : 'heroicon-o-puzzle-piece'"
                    :color="$isCurrent ? 'primary' : 'gray'"
                    :aria-current="$isCurrent ? 'page' : false"
                >
                    {{ $panelLabel($panel) }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
