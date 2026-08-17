{{--
    The list column's head: enable-state tabs with the category filter parked at
    their far end, then the search on its own row.

    The population tabs (Installed / Updates / Registry) are NOT here — they are
    NavigationItems, so the topbar's workspace navigator renders them and this
    column keeps a single tab row.

    Enable state is a tab rather than a divider inside the list: as a divider it
    read as a scope the search box sat outside of. Search gets the whole row for
    the same reason — next to a filter chip it reads as one more filter chip.
--}}
@php
    $states = $this->stateCounts();
@endphp

<div class="addon-toolbar">
    <div class="subtabs">
        @foreach (['all', 'enabled', 'disabled'] as $key)
            <button type="button" class="subtab" wire:click="$set('state', @js($key))"
                aria-selected="{{ $this->state === $key ? 'true' : 'false' }}">
                {{ __('addon-manager::addons.state_'.$key) }}<em>{{ $states[$key] }}</em>
            </button>
        @endforeach

        <span class="subtabs__aside">
            <span class="field">
                <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::FunnelLight" />
                <label class="sr-only" for="addon-category">{{ __('addon-manager::addons.all_categories') }}</label>
                <select id="addon-category" wire:model.live="category">
                    <option value="">{{ __('addon-manager::addons.all_categories') }}</option>
                    @foreach ($this->categories() as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </span>
        </span>
    </div>

    <div class="filters">
        <label class="field field--search">
            <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::MagnifyingGlassLight" />
            <span class="sr-only">{{ __('addon-manager::addons.search_addons', ['count' => $states['all']]) }}</span>
            <input type="search" wire:model.live.debounce.300ms="search"
                placeholder="{{ __('addon-manager::addons.search_addons', ['count' => $states['all']]) }}">
        </label>
    </div>
</div>
