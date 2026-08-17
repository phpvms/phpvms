{{--
    "phpvms" — the way back out of the admin panel to the pilot-facing
    dashboard. Emits the vendor's own .fi-sidebar-item DOM so it inherits both
    sidebar states for free: a labelled row when open, an icon over a small
    caption when the rail is collapsed.

    Rendered via PanelsRenderHook::SIDEBAR_NAV_END, which is the last thing
    inside the <nav> and therefore sits directly above the user-menu footer.
    Filament exposes no hook inside .fi-sidebar-footer itself.
--}}
<div class="fi-sidebar-item fi-sidebar-item-has-url fi-back-to-site">
    <a href="{{ route('frontend.dashboard.index') }}" class="fi-sidebar-item-btn">
        <x-filament::icon
            :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::ArrowLeftLight"
            class="fi-sidebar-item-icon"
        />

        <span class="fi-sidebar-item-label">
            {{ __('filament.back_to_site') }}
        </span>
    </a>
</div>
