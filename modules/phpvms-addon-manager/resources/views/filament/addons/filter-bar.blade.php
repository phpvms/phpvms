{{--
    One-row filter bar (C1). Writes the table's real state — `tableSearch` and
    `tableFilters` — so records() and the filter indicators keep working; the
    native search field and filter trigger are hidden. Status stays in the
    banner tabs.
--}}
@php
    use Filafly\Icons\Phosphor\Enums\Phosphor;

    $category = $this->tableFilters['category']['value'] ?? null;
    $official = (bool) ($this->tableFilters['official']['isActive'] ?? false);
    $hideUntested = (bool) ($this->tableFilters['hide_untested']['isActive'] ?? false);
@endphp

<div class="addon-bar">
    <x-filament::input.wrapper class="addon-bar__search" :prefix-icon="Phosphor::MagnifyingGlassLight">
        <x-filament::input type="search" wire:model.live.debounce.400ms="tableSearch"
            :placeholder="__('addon-manager::addons.search_addons_short')" />
    </x-filament::input.wrapper>

    <x-filament::dropdown placement="bottom-start">
        <x-slot name="trigger">
            <x-filament::button color="gray" size="sm" :icon="Phosphor::FunnelLight">
                {{ __('addon-manager::addons.category') }}: {{ $category ?: __('addon-manager::addons.any') }}
            </x-filament::button>
        </x-slot>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item wire:click="setCategoryFilter(null)" :icon="$category === null ? Phosphor::CheckLight : null">
                {{ __('addon-manager::addons.any') }}
            </x-filament::dropdown.list.item>

            @foreach ($this->categoryCounts() as $name => $count)
                <x-filament::dropdown.list.item wire:key="addon-cat-{{ $name }}"
                    wire:click="setCategoryFilter({{ \Illuminate\Support\Js::from($name) }})"
                    :icon="$category === $name ? Phosphor::CheckLight : null">
                    {{ $name }} ({{ $count }})
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>

    <button type="button" class="addon-bar__pill" aria-pressed="{{ $official ? 'true' : 'false' }}"
        wire:click="toggleFilterFlag('official')">
        <x-filament::icon :icon="Phosphor::ShieldCheckLight" />
        {{ __('addon-manager::addons.official') }}
    </button>

    <button type="button" class="addon-bar__pill" aria-pressed="{{ $hideUntested ? 'true' : 'false' }}"
        wire:click="toggleFilterFlag('hide_untested')">
        {{ __('addon-manager::addons.hide_untested') }}
    </button>

    <span class="addon-bar__sort">
        <x-filament::icon :icon="Phosphor::ArrowDownLight" />
        {{ __('addon-manager::addons.sort_name') }}
    </span>
</div>
