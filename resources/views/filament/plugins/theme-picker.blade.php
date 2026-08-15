{{--
    Console theme picker: appearance and density, applied client-side by
    resources/js/apps/admin/theme-picker.js. Ported from the mockup's picker
    (mockups/admin-console-v2/theme-picker.js), trimmed further still — brand
    colour moved to the Branding admin page (site-wide, not per-user), so only
    appearance and density remain here.
--}}
<x-filament::dropdown placement="bottom-end" width="none" class="fi-theme-picker">
    <x-slot name="trigger">
        <button type="button" class="fi-icon-btn fi-theme-picker-btn" aria-label="Theme settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="8.5" />
                <circle cx="9" cy="9.5" r="1.1" fill="currentColor" stroke="none" />
                <circle cx="14.5" cy="9" r="1.1" fill="currentColor" stroke="none" />
                <circle cx="16" cy="14" r="1.1" fill="currentColor" stroke="none" />
                <path d="M12 20.5c-1 0-1.5-.8-1.1-1.6.5-1 .1-1.9-1-1.9H8" />
            </svg>
        </button>
    </x-slot>

    <div class="fi-picker-group">
        <span class="fi-picker-label">Appearance</span>
        <div class="fi-picker-segmented" role="group" aria-label="Appearance">
            <button type="button" data-mode="light" aria-pressed="false">Light</button>
            <button type="button" data-mode="dark" aria-pressed="false">Dark</button>
        </div>
    </div>

    <div class="fi-picker-group">
        <span class="fi-picker-label">Density</span>
        <div class="fi-picker-segmented" role="group" aria-label="Density">
            <button type="button" data-density="compact" aria-pressed="false">Compact</button>
            <button type="button" data-density="comfortable" aria-pressed="false">Comfortable</button>
        </div>
    </div>
</x-filament::dropdown>
