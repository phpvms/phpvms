<?php

declare(strict_types=1);

namespace App\Filament\Resources\MapLayers\Schemas;

use App\Filament\Actions\TestMapStyleAction;
use App\Filament\Resources\MapLayers\Pages\ListMapLayers;
use App\Models\MapLayer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The light/dark basemap pair, shared by the "Base Maps" drawer on
 * {@see ListMapLayers} and by the
 * basemap-only fields of {@see MapLayerForm}.
 *
 * This configuration used to live in the `map` settings group and render on
 * the Settings page; it moved into `map_layers` so the whole map
 * configuration is edited in one place.
 *
 * A basemap is the one layer type needing TWO urls: the surface swaps style
 * with the panel theme, so `url_template` holds the light style and
 * `url_template_dark` the dark one (design.md D8).
 */
class BasemapForm
{
    /**
     * Not a real style URL — the value a select carries while the operator has
     * chosen to type their own URL instead of picking from the curated list.
     * Swapped for the typed value on save, so what reaches the column (and
     * therefore `MapConfigService::resolve()`) is always a usable style.
     */
    public const string CUSTOM = '__custom__';

    /**
     * Not a fetchable URL — a sentinel the map package's style resolver
     * recognises as "use the bundled, themed ESRI World Imagery style" rather
     * than fetching a style.json. Matches
     * `resources/js/packages/map/src/basemaps.ts`'s `ESRI_SENTINEL`.
     */
    public const string ESRI = 'esri-world-imagery';

    /**
     * phpvms's own vector-tile service. These string literals are a contract
     * with `basemaps.ts`'s `VACENTRAL_LIGHT_URL`/`VACENTRAL_DARK_URL`, not a
     * local choice — keep them in sync with that file. A mismatch falls
     * through to the "custom URL" branch of `style.ts`'s `resolveStyle()`
     * rather than failing loudly.
     */
    public const string VACENTRAL_LIGHT = 'https://tiles.vacentral.net/styles/light/style.json';

    public const string VACENTRAL_DARK = 'https://tiles.vacentral.net/styles/dark/style.json';

    private const string CARTO_VOYAGER = 'https://basemaps.cartocdn.com/gl/voyager-nolabels-gl-style/style.json';

    private const string CARTO_DARK_MATTER = 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json';

    /**
     * The two style selects plus the custom-URL field each can reveal.
     *
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            Select::make('url_template')
                ->label(__('map.basemap_light'))
                ->helperText(__('map.basemap_light_hint'))
                ->options(self::curatedOptions(self::VACENTRAL_LIGHT))
                ->default(self::VACENTRAL_LIGHT)
                ->native(false)
                ->live()
                ->required(),

            TextInput::make('custom_url_light')
                ->label(__('map.basemap_custom_url_light'))
                ->visible(fn (Get $get): bool => $get('url_template') === self::CUSTOM)
                ->required(fn (Get $get): bool => $get('url_template') === self::CUSTOM)
                ->hintAction(TestMapStyleAction::make('custom_url_light'))
                ->string(),

            Select::make('url_template_dark')
                ->label(__('map.basemap_dark'))
                ->helperText(__('map.basemap_dark_hint'))
                ->options(self::curatedOptions(self::VACENTRAL_DARK))
                ->default(self::VACENTRAL_DARK)
                ->native(false)
                ->live()
                ->required(),

            TextInput::make('custom_url_dark')
                ->label(__('map.basemap_custom_url_dark'))
                ->visible(fn (Get $get): bool => $get('url_template_dark') === self::CUSTOM)
                ->required(fn (Get $get): bool => $get('url_template_dark') === self::CUSTOM)
                ->hintAction(TestMapStyleAction::make('custom_url_dark'))
                ->string(),
        ];
    }

    /**
     * The curated, key-free list plus the "Custom style…" sentinel.
     *
     * vacentral is the one option that genuinely differs between the light and
     * dark side — it is a real fetchable URL per theme, so the caller passes
     * whichever of VACENTRAL_LIGHT/VACENTRAL_DARK matches the field being
     * built. Voyager and Dark Matter stay on both sides: an operator picking
     * "the dark one" for the light field isn't something this needs to police.
     *
     * @return array<string, string>
     */
    public static function curatedOptions(string $vacentralUrl): array
    {
        return [
            self::CARTO_VOYAGER     => __('map.basemap_voyager'),
            self::CARTO_DARK_MATTER => __('map.basemap_dark_matter'),
            $vacentralUrl           => __('map.basemap_vacentral'),
            self::ESRI              => __('map.basemap_esri'),
            self::CUSTOM            => __('map.basemap_custom'),
        ];
    }

    /**
     * Row columns → form state. A stored URL that isn't curated is the
     * operator's own, so the select shows "Custom style…" and the text field
     * carries the URL; otherwise the select shows the curated entry.
     *
     * @return array<string, string|null>
     */
    public static function toFormState(?MapLayer $basemap): array
    {
        return [
            'url_template'      => self::selectValue($basemap?->url_template, self::VACENTRAL_LIGHT),
            'custom_url_light'  => self::customValue($basemap?->url_template, self::VACENTRAL_LIGHT),
            'url_template_dark' => self::selectValue($basemap?->url_template_dark, self::VACENTRAL_DARK),
            'custom_url_dark'   => self::customValue($basemap?->url_template_dark, self::VACENTRAL_DARK),
        ];
    }

    /**
     * Form state → row columns, resolving the "Custom style…" sentinel so the
     * column never stores it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function toColumns(array $data): array
    {
        return [
            'url_template'      => self::resolveUrl($data, 'url_template', 'custom_url_light'),
            'url_template_dark' => self::resolveUrl($data, 'url_template_dark', 'custom_url_dark'),
        ];
    }

    /** @param array<string, mixed> $data */
    private static function resolveUrl(array $data, string $selectKey, string $customKey): string
    {
        $selected = (string) ($data[$selectKey] ?? '');

        return $selected === self::CUSTOM ? (string) ($data[$customKey] ?? '') : $selected;
    }

    private static function selectValue(?string $stored, string $vacentralUrl): string
    {
        if ($stored === null || $stored === '') {
            return $vacentralUrl;
        }

        return array_key_exists($stored, self::curatedOptions($vacentralUrl)) ? $stored : self::CUSTOM;
    }

    private static function customValue(?string $stored, string $vacentralUrl): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return array_key_exists($stored, self::curatedOptions($vacentralUrl)) ? null : $stored;
    }
}
