{{--
    UTC clock pinned to the head of the module rail, with the sidebar
    collapse toggle beside it (below it in the icon rail).

    The zone label is a button that toggles UTC/local.

    Ticked client-side by resources/js/apps/admin/utc-clock.js; rendered empty here
    so there is no server-rendered timestamp to go stale before hydration.

    Rendered via PanelsRenderHook::SIDEBAR_START.
--}}
<div class="fi-rail-clock">
    <div class="fi-rail-clock-face">
        <time aria-hidden="true"></time>
        <button type="button" class="fi-rail-clock-zone">UTC</button>
    </div>
    @include('filament.shared.sidebar-collapse-toggle')
</div>
