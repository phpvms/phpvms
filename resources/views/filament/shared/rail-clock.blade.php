{{--
    UTC clock pinned to the head of the module rail, with the sidebar
    collapse toggle beside it (below it in the icon rail).

    Ticked client-side by resources/js/apps/admin/utc-clock.js; rendered empty here
    so there is no server-rendered timestamp to go stale before hydration.

    Rendered via PanelsRenderHook::SIDEBAR_START.
--}}
<div class="fi-rail-clock">
    <div class="fi-rail-clock-face" aria-hidden="true">
        <time></time>
        <span class="fi-rail-clock-zone">UTC</span>
    </div>
    @include('filament.shared.sidebar-collapse-toggle')
</div>
