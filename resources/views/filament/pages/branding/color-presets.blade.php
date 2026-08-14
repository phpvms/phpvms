@php
    /**
     * Admin Panel Colors: a grid of Filament's built-in Tailwind palettes.
     *
     * Storage format (see App\Support\Branding::brandPalette()):
     * `branding.brand_color` holds EITHER a lowercase palette name (e.g.
     * "blue") OR a hex string (e.g. "#4f46e5"). Clicking a swatch below
     * writes the palette name; the sibling ColorPicker (custom colour)
     * writes a hex through its own afterStateUpdated() hook.
     *
     * @var array<string, array<int, string>> $palettes name => [50 => 'oklch(...)', ..., 950 => 'oklch(...)']
     * @var string $selected lowercased current value of branding.brand_color
     */
@endphp

{{--
    Live preview. Nothing here persists -- this only paints --primary-* on
    <html> while the admin is choosing, and a reload drops back to whatever
    is stored. See App\Filament\Pages\Branding::getFormSchema() for the full
    storage-format writeup.

    Two preview paths:
    - A palette swatch click sets --primary-* to that palette's own exact
      50-950 shades (emitted server-side into `palettes` below), which is
      exactly what `AdminPanelProvider` renders once saved.
    - The custom ColorPicker approximates instead, via the same linear
      oklab mix the old topbar theme picker used -- the picker has no
      server round trip while dragging, so there is no exact ramp to read.

    Two LISTENERS, because Filament's ColorPicker has two ways to change:
    - Dragging happens inside a <hex-color-picker> web component
      (vanilla-colorful, rendered at ColorPicker.php:184). It never fires
      `input` on an <input> -- it dispatches a `color-changed` CustomEvent
      with `bubbles: true`, which is also how Filament's own
      color-picker.js reads it.
    - Typing a hex into the text box fires `change` on it, and setting the
      picker's `.color` property programmatically does NOT re-emit
      `color-changed`, so that case needs its own listener.

    Both are bound with Alpine's `.document` modifier rather than a manual
    addEventListener, so Alpine removes them when this node is torn down --
    a Livewire re-render would otherwise stack a duplicate listener each
    time.
--}}
<div
    x-data="{
        palettes: {{ Illuminate\Support\Js::from($palettes) }},

        previewPalette(name) {
            const palette = this.palettes[name];
            if (!palette) {
                return;
            }

            const root = document.documentElement.style;

            for (const [shade, value] of Object.entries(palette)) {
                root.setProperty(`--primary-${shade}`, value);
            }
        },

        shades: {
            50: [4, 'white'], 100: [8, 'white'], 200: [17, 'white'],
            300: [30, 'white'], 400: [45, 'white'], 500: [70, 'white'],
            600: [100, 'white'], 700: [85, 'black'], 800: [70, 'black'],
            900: [55, 'black'], 950: [35, 'black'],
        },

        previewHex(hex) {
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
    }"
    x-on:color-changed.document="previewHex($event.detail?.value)"
    x-on:change.document="$event.target.matches('.fi-fo-color-picker input') && previewHex($event.target.value)"
>
    <div class="fi-picker-swatches" role="group" aria-label="{{ __('filament.branding_admin_colors') }}">
        @foreach ($palettes as $name => $shades)
            <button
                type="button"
                class="fi-picker-swatch"
                title="{{ ucfirst($name) }}"
                aria-label="{{ ucfirst($name) }}"
                aria-pressed="{{ $selected === $name ? 'true' : 'false' }}"
                style="background:{{ $shades[600] }};color:{{ $shades[600] }}"
                x-on:click="
                    $set('branding.brand_color', '{{ $name }}', false, true);
                    previewPalette('{{ $name }}');
                "
            ></button>
        @endforeach
    </div>
</div>
