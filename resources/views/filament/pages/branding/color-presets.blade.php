@php
    /**
     * Brand colour presets, moved here from the topbar theme picker
     * (formerly resources/views/filament/plugins/theme-picker.blade.php).
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

<div class="fi-picker-swatches mt-2">
    @foreach ($presets as $hex => $name)
        <button
            type="button"
            class="fi-picker-swatch"
            title="{{ $name }}"
            aria-label="{{ $name }}"
            style="background:{{ $hex }};color:{{ $hex }}"
            x-on:click="$set('branding.brand_color', '{{ $hex }}', false, true)"
        ></button>
    @endforeach
</div>
