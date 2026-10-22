<?php

declare(strict_types=1);

use App\Models\MapLayer;

/**
 * Runs the data migration directly, matching the convention established by
 * BrandingSettingsMigrationTest: `require`'d rather than executed through
 * the migrator, so `up()`/`down()` can be called and asserted on directly.
 */
function seedMapLayersMigration(): object
{
    return require base_path('database/migrations_data/2026_08_31_000000_seed_map_layers.php');
}

it('seeds the OpenAIP layer disabled when no key is configured', function (): void {
    config(['services.openaip.api_key' => '']);

    seedMapLayersMigration()->up();

    $layer = MapLayer::query()->where('name', 'OpenAIP Airspace')->first();

    expect($layer)->not->toBeNull()
        ->and($layer->type)->toBe(MapLayer::TYPE_RASTER)
        ->and($layer->enabled)->toBeFalse()
        ->and($layer->api_key)->toBeNull();
});

it('seeds the OpenAIP layer enabled and carries the configured key when set', function (): void {
    // The migration reads config('services.openaip.api_key') rather than
    // env() directly (env() outside config/ returns null once config is
    // cached), which is the same value AdminPanelProvider.php:185 ships to
    // the browser today and config/services.php:83 resolves from
    // OPENAIP_API_KEY.
    config(['services.openaip.api_key' => 'test-key-123']);

    seedMapLayersMigration()->up();

    $layer = MapLayer::query()->where('name', 'OpenAIP Airspace')->first();

    expect($layer)->not->toBeNull()
        ->and($layer->enabled)->toBeTrue()
        ->and($layer->api_key)->toBe('test-key-123');
});

it('seeds the METAR WMS layer as a disabled raster layer with a bbox placeholder', function (): void {
    seedMapLayersMigration()->up();

    $layer = MapLayer::query()->where('name', 'METAR')->first();

    // Disabled on purpose: the overlay reaches four new surfaces once it is a
    // layer row, and it calls a third-party service this project does not run.
    // Operators opt in from the admin panel. See the migration's own comment.
    expect($layer)->not->toBeNull()
        ->and($layer->type)->toBe(MapLayer::TYPE_RASTER)
        ->and($layer->enabled)->toBeFalse()
        ->and($layer->url_template)->toContain('{bbox-epsg-3857}')
        ->and($layer->url_template)->toContain('layers=metar')
        ->and($layer->url_template)->toStartWith((string) config('phpvms.metar_wms.url'));
});

it('is idempotent: running the migration twice does not duplicate rows', function (): void {
    seedMapLayersMigration()->up();
    seedMapLayersMigration()->up();

    expect(MapLayer::query()->where('name', 'OpenAIP Airspace')->count())->toBe(1)
        ->and(MapLayer::query()->where('name', 'METAR')->count())->toBe(1);
});

it('does not touch an operator-edited row on re-run', function (): void {
    seedMapLayersMigration()->up();

    $layer = MapLayer::query()->where('name', 'METAR')->firstOrFail();
    $layer->update(['opacity' => 0.5, 'enabled' => false]);

    seedMapLayersMigration()->up();

    $layer->refresh();

    expect($layer->opacity)->toBe(0.5)
        ->and($layer->enabled)->toBeFalse();
});

it('down() removes exactly the two seeded rows', function (): void {
    MapLayer::factory()->create(['name' => 'Operator Added']);

    seedMapLayersMigration()->up();
    seedMapLayersMigration()->down();

    expect(MapLayer::query()->where('name', 'OpenAIP Airspace')->exists())->toBeFalse()
        ->and(MapLayer::query()->where('name', 'METAR')->exists())->toBeFalse()
        ->and(MapLayer::query()->where('name', 'Operator Added')->exists())->toBeTrue();
});
