@php
    /**
     * Brand colour presets, ported from mockups/admin-console-v2/theme-picker.js:17-26.
     *
     * @var array<string, string> $presets
     */
    $presets = [
        '#067ec1' => 'phpVMS blue',
        '#4f46e5' => 'Indigo',
        '#7c3aed' => 'Violet',
        '#0d9488' => 'Teal',
        '#059669' => 'Emerald',
        '#b45309' => 'Amber',
        '#e11d48' => 'Rose',
        '#475569' => 'Slate',
    ];
@endphp

{{--
    Console theme picker: brand colour, appearance and density, applied
    client-side by resources/js/admin/theme-picker.js. Ported from the
    mockup's picker (mockups/admin-console-v2/theme-picker.js), trimmed to
    the three sections above — canvas, page header, glass headers and the
    "what this writes" output are intentionally left out.
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
        <span class="fi-picker-label">Brand colour</span>
        <div class="fi-picker-swatches">
            @foreach ($presets as $hex => $name)
                <button
                    type="button"
                    class="fi-picker-swatch"
                    data-preset="{{ $hex }}"
                    title="{{ $name }}"
                    aria-label="{{ $name }}"
                    aria-pressed="false"
                    style="background:{{ $hex }};color:{{ $hex }}"
                ></button>
            @endforeach
        </div>
        <div class="fi-picker-hex">
            <input type="color" data-hex-color aria-label="Pick a custom brand colour" />
            <input type="text" data-hex-text maxlength="7" spellcheck="false" aria-label="Brand colour hex" />
        </div>
        <p class="fi-picker-hint">Every accent shade is mixed from this one value, so dark mode needs no second palette.</p>
    </div>

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
