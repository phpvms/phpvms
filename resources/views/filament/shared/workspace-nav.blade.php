{{--
    Workspace navigator.

    The console shows the active module's name and its items as horizontal
    tabs in the topbar, so moving inside a module never needs the rail. The
    items are the same NavigationItem objects the rail renders, read back from
    the panel, so anything an add-on registers appears here too.

    Rendered via PanelsRenderHook::TOPBAR_LOGO_AFTER.
--}}
@php
    $activeGroup = null;

    foreach (filament()->getNavigation() as $group) {
        foreach ($group->getItems() as $item) {
            if ($item->isActive()) {
                $activeGroup = $group;

                break 2;
            }
        }
    }

    $items = $activeGroup?->getItems() ?? [];
@endphp

@if (filled($items))
    <div class="fi-workspace-nav">
        @if (filled($activeGroup?->getLabel()))
            <span class="fi-workspace-nav-module">
                @if ($groupIcon = $activeGroup->getIcon())
                    <x-filament::icon :icon="$groupIcon" />
                @endif
                {{ $activeGroup->getLabel() }}
            </span>
        @endif

        <nav class="fi-workspace-nav-tabs" aria-label="{{ $activeGroup?->getLabel() }}">
            @foreach ($items as $item)
                @php
                    $badge = $item->getBadge();
                @endphp

                <a
                    href="{{ $item->getUrl() }}"
                    class="fi-workspace-nav-tab"
                    @if ($item->isActive()) aria-current="page" @endif
                    @if ($item->shouldOpenUrlInNewTab()) target="_blank" @endif
                >
                    @if ($itemIcon = $item->getIcon())
                        <x-filament::icon :icon="$itemIcon" />
                    @endif

                    {{ $item->getLabel() }}

                    @if (filled($badge))
                        <span class="fi-workspace-nav-tab-badge">{{ $badge }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
@endif
