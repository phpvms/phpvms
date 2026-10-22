<?php

use App\Models\MapLayer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the two overlays that already exist today, bespokely, as `map_layers`
 * rows (design.md D9, spec `map-configuration` "Existing overlays migrate
 * into the table").
 *
 * OpenAIP: carries over the key from `config('services.openaip.api_key')`
 * (resolved from `OPENAIP_API_KEY`; read via `config()` rather than `env()`
 * directly, since `env()` returns null once config is cached — see
 * `AdminPanelProvider.php:185`, which reads the same value today) where it
 * is set, so an upgrade keeps rendering the overlay without operator action.
 * Where the key is unset, the row is seeded disabled — the base map still
 * renders, matching `admin/maps/base_map.js:37-38`'s silent no-op today.
 *
 * METAR WMS: seeded from `config('phpvms.metar_wms')`
 * (`config/phpvms.php:294-299`) as an ordinary raster row whose URL template
 * carries the WMS query string, per D9 ("WMS is not a third type"). That
 * config key is left in place for the seven theme, which is out of this
 * change's scope and keeps reading it directly.
 *
 * Idempotent: matched by `name`, so re-running (or running on an install that
 * already has these rows) does not duplicate them. An operator's own edits
 * to `enabled`, `opacity`, etc. on an existing row are left untouched — only
 * missing rows are inserted.
 */
return new class() extends Migration
{
    private const string OPENAIP_NAME = 'OpenAIP Airspace';

    private const string METAR_NAME = 'METAR';

    public function up(): void
    {
        if (!Schema::hasTable('map_layers')) {
            return;
        }

        if (!MapLayer::query()->where('name', self::OPENAIP_NAME)->exists()) {
            $apiKey = (string) config('services.openaip.api_key', '');

            MapLayer::query()->create([
                'name'         => self::OPENAIP_NAME,
                'type'         => MapLayer::TYPE_RASTER,
                'url_template' => 'https://api.tiles.openaip.net/api/data/openaip/{z}/{x}/{y}.png?apiKey={apiKey}',
                'attribution'  => '<a href="https://www.openaip.net/" target="_blank">OpenAIP</a> — airspace data CC BY-NC-SA',
                'min_zoom'     => 4,
                'max_zoom'     => 14,
                'opacity'      => 0.9,
                'enabled'      => $apiKey !== '',
                'order'        => 0,
                'api_key'      => $apiKey !== '' ? $apiKey : null,
                'surfaces'     => null,
            ]);
        }

        if (!MapLayer::query()->where('name', self::METAR_NAME)->exists()) {
            $metarWms = (array) config('phpvms.metar_wms', []);
            $baseUrl = (string) ($metarWms['url'] ?? 'https://ogcie.iblsoft.com/observations?');
            $layers = (string) ($metarWms['params']['layers'] ?? 'metar');

            MapLayer::query()->create([
                'name'         => self::METAR_NAME,
                'type'         => MapLayer::TYPE_RASTER,
                'url_template' => $baseUrl.'service=WMS&request=GetMap&version=1.1.1&layers='.$layers
                    .'&styles=&format=image/png&transparent=true&srs=EPSG:3857&width=256&height=256&bbox={bbox-epsg-3857}',
                'attribution' => 'METAR observations (ogcie.iblsoft.com)',
                'min_zoom'    => 4,
                'max_zoom'    => 14,
                'opacity'     => 1,
                // Seeded DISABLED, deliberately. `config/phpvms.php`'s `metar_wms` is a
                // long-standing phpVMS default pointing at a third-party service nobody
                // here operates (ogcie.iblsoft.com), and until this change it only reached
                // two seven-theme blades. Turning it into a layer row would otherwise put
                // it on the admin PIREP map, admin live flights, the skylight live map and
                // the briefing globe at once — new visual behaviour, and a new outbound
                // request on page loads that never made one. Operators opt in from the
                // admin panel instead; seven's own two pages read the config directly and
                // are unaffected either way.
                'enabled'  => false,
                'order'    => 1,
                'api_key'  => null,
                'surfaces' => null,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('map_layers')) {
            return;
        }

        MapLayer::query()->whereIn('name', [self::OPENAIP_NAME, self::METAR_NAME])->delete();
    }
};
