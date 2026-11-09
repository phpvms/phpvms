{{--
    Topbar stand-in for Filament's own search field (hidden in theme.css):
    looks the same, but opens the command palette instead of a dropdown.
    Rendered via PanelsRenderHook::GLOBAL_SEARCH_BEFORE.
--}}
<button type="button" class="cmdk-trigger" x-data x-on:click="$dispatch('cmdk-open')" aria-label="{{ __('filament.palette_label') }}">
    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::MagnifyingGlassLight" class="cmdk-trigger-icon" />
    <span class="cmdk-trigger-text">{{ __('filament.palette_trigger') }}</span>
    <kbd class="cmdk-kbd" x-text="navigator.platform.includes('Mac') ? '⌘K' : 'Ctrl K'">⌘K</kbd>
</button>
