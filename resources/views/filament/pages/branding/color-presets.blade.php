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

{{--
    Live preview. The saved palette comes from Color::generatePalette() rendered
    server-side into a :root block, so nothing here persists -- this only paints
    --primary-* on <html> while the admin is choosing, and a reload drops back to
    whatever is stored.

    The shade curve is the one the old topbar picker used: a linear oklab mix,
    not Filament's perceptually tuned ramp, so the preview is close but not
    identical to the saved result. Good enough to judge a colour by; upgrade the
    curve if the two ever visibly diverge.
--}}
<div
    class="fi-picker-swatches mt-2"
    x-data="{
        shades: {
            50: [4, 'white'], 100: [8, 'white'], 200: [17, 'white'],
            300: [30, 'white'], 400: [45, 'white'], 500: [70, 'white'],
            600: [100, 'white'], 700: [85, 'black'], 800: [70, 'black'],
            900: [55, 'black'], 950: [35, 'black'],
        },

        preview(hex) {
            if (!/^#[0-9a-fA-F]{6}$/.test(hex ?? '')) {
                return;
            }

            const root = document.documentElement.style;

            for (const [shade, [percent, mix]] of Object.entries(this.shades)) {
                root.setProperty(
                    `--primary-${shade}`,
                    percent >= 100 ? hex : `color-mix(in oklab, ${hex} ${percent}%, ${mix})`,
                );
            }
        },

        init() {
            // The ColorPicker's own inputs live in the field above this partial,
            // not inside it, so reach up to the shared field wrapper. `input`
            // fires continuously while the native picker is dragged, which is
            // what makes this feel live.
            const field = this.$el.closest('.fi-fo-field, .fi-fo-field-wrp');

            field?.querySelectorAll('input').forEach((input) => {
                input.addEventListener('input', (event) => this.preview(event.target.value));
            });
        },
    }"
>
    @foreach ($presets as $hex => $name)
        <button
            type="button"
            class="fi-picker-swatch"
            title="{{ $name }}"
            aria-label="{{ $name }}"
            style="background:{{ $hex }};color:{{ $hex }}"
            x-on:click="
                $set('branding.brand_color', '{{ $hex }}', false, true);
                preview('{{ $hex }}');
            "
        ></button>
    @endforeach
</div>
