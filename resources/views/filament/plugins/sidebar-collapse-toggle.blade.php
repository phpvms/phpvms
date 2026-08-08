<div
    class="fi-sidebar-collapse-toggle"
    x-data="{}"
    x-cloak
>
    <x-filament::icon-button
        color="gray"
        size="lg"
        :icon="\Daljo25\FilamentTablerIcons\Enums\TablerIcon::LayoutSidebar"
        :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
        x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
    />
</div>
