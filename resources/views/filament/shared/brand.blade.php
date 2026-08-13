@php
    // Shared by every airline panel (via BasePanelProvider) AND the vendor
    // `system` panel (SystemPanelProvider). The system panel must keep
    // showing phpVMS branding, not the airline's -- it is the vendor panel.
    $isSystemPanel = \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'system';
    $branding = app(\App\Support\Branding::class);
@endphp

<div class="flex items-center gap-3">
    <img
        src="{{ $isSystemPanel ? asset('assets/img/logo_blue.svg') : $branding->logo() }}"
        alt="{{ $isSystemPanel ? 'phpvms' : $branding->name() }}"
        class="h-12 w-auto"
    />
    <span
        class="font-display text-[27.75px] font-semibold tracking-[-0.01em] text-[#444950] dark:text-[#e3e3e3]"
    >
        {{ $isSystemPanel ? __('installer.title') : $branding->name() }}
    </span>
</div>
