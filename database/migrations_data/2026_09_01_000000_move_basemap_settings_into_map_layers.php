<?php

use App\Models\MapLayer;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the basemap pair out of the `map` settings group and into a
 * `map_layers` row of the new `basemap` type, so the whole map configuration
 * — basemap and overlays — is edited on one admin screen instead of two.
 *
 * Carries the operator's existing choice over rather than resetting it to the
 * default: `map.basemap_light`/`map.basemap_dark` already store a real,
 * resolved style URL (the Settings page swapped its `__custom__` sentinel for
 * the typed URL on save), so the values transfer verbatim.
 *
 * `map.custom_style_url` goes too — a custom style is now just a URL typed
 * straight into the basemap row's own field, so a separate setting for it has
 * nothing left to hold. `map.custom_style_api_key` STAYS: `MapConfigData`
 * still carries it for a caller that wants to inject a key into a custom
 * style (see `style.ts`'s `resolveStyle` docblock).
 *
 * Idempotent: does nothing if a basemap row already exists, so re-running it
 * cannot clobber an operator's later edits.
 */
return new class() extends Migration
{
    private const string LIGHT_DEFAULT = 'https://tiles.vacentral.net/styles/light/style.json';

    private const string DARK_DEFAULT = 'https://tiles.vacentral.net/styles/dark/style.json';

    /** @var list<string> */
    private const array RETIRED_KEYS = ['map.basemap_light', 'map.basemap_dark', 'map.custom_style_url'];

    public function up(): void
    {
        if (!Schema::hasTable('map_layers') || !Schema::hasTable('settings')) {
            return;
        }

        if (!MapLayer::query()->where('type', MapLayer::TYPE_BASEMAP)->exists()) {
            MapLayer::query()->create([
                'name'              => 'Base Maps',
                'type'              => MapLayer::TYPE_BASEMAP,
                'url_template'      => $this->settingValue('map.basemap_light', self::LIGHT_DEFAULT),
                'url_template_dark' => $this->settingValue('map.basemap_dark', self::DARK_DEFAULT),
                'attribution'       => null,
                'enabled'           => true,
                // Ahead of the seeded overlays so it reads first in the table.
                'order' => 0,
            ]);
        }

        Setting::query()->whereIn('key', self::RETIRED_KEYS)->delete();
    }

    private function settingValue(string $key, string $default): string
    {
        $value = (string) (Setting::query()->where('key', $key)->value('value') ?? '');

        return $value === '' ? $default : $value;
    }
};
