<?php

declare(strict_types=1);

use App\Http\Data\MapLayerData;
use App\Models\MapLayer;
use App\Services\MapConfigService;

it('resolves the basemap pair from the active basemap row', function (): void {
    MapLayer::factory()->basemap()->create();

    $config = app(MapConfigService::class)->resolve();

    expect($config->basemapLight)->toBe('https://tiles.example.com/styles/light/style.json')
        ->and($config->basemapDark)->toBe('https://tiles.example.com/styles/dark/style.json')
        ->and($config->customStyleApiKey)->toBeNull();
});

it('falls back to the light style when the basemap row has no dark one', function (): void {
    MapLayer::factory()->basemap()->create(['url_template_dark' => null]);

    $config = app(MapConfigService::class)->resolve();

    expect($config->basemapDark)->toBe('https://tiles.example.com/styles/light/style.json');
});

it('resolves an empty pair when no basemap row exists, rather than fatalling', function (): void {
    $config = app(MapConfigService::class)->resolve();

    expect($config->basemapLight)->toBe('')
        ->and($config->basemapDark)->toBe('');
});

it('uses the first enabled basemap row in order, ignoring a disabled one', function (): void {
    MapLayer::factory()->basemap()->disabled()->create([
        'order'        => 0,
        'url_template' => 'https://disabled.example.com/style.json',
    ]);
    MapLayer::factory()->basemap()->create([
        'order'        => 1,
        'url_template' => 'https://live.example.com/style.json',
    ]);

    expect(app(MapConfigService::class)->resolve()->basemapLight)
        ->toBe('https://live.example.com/style.json');
});

it('keeps the basemap row out of the overlay list — applyLayers would treat it as a tile source', function (): void {
    MapLayer::factory()->basemap()->create(['name' => 'Base Maps']);
    MapLayer::factory()->create(['name' => 'An overlay']);

    $config = app(MapConfigService::class)->resolve();

    expect($config->layers)->toHaveCount(1)
        ->and($config->layers[0]->name)->toBe('An overlay');
});

it('resolves a configured custom style api key', function (): void {
    setting_save('map.custom_style_api_key', 'secret-key');

    expect(app(MapConfigService::class)->resolve()->customStyleApiKey)->toBe('secret-key');
});

it('includes only enabled layers, ordered', function (): void {
    MapLayer::factory()->create(['name' => 'Second', 'order' => 2, 'enabled' => true]);
    MapLayer::factory()->create(['name' => 'First', 'order' => 1, 'enabled' => true]);
    MapLayer::factory()->disabled()->create(['name' => 'Hidden', 'order' => 0]);

    $config = app(MapConfigService::class)->resolve();

    expect($config->layers)->toHaveCount(2)
        ->and(array_column($config->layers, 'name'))->toBe(['First', 'Second']);
});

it('maps a layer row to the camelCase MapLayerData wire shape', function (): void {
    $layer = MapLayer::factory()->create([
        'name'         => 'Test Layer',
        'type'         => MapLayer::TYPE_RASTER,
        'url_template' => 'https://example.com/{z}/{x}/{y}.png',
        'attribution'  => 'Test Attribution',
        'min_zoom'     => 2,
        'max_zoom'     => 18,
        'opacity'      => 0.75,
        'api_key'      => 'abc123',
        'surfaces'     => ['pirep_view'],
    ]);

    $config = app(MapConfigService::class)->resolve();

    expect($config->layers)->toHaveCount(1);

    $layerData = $config->layers[0];

    expect($layerData)->toBeInstanceOf(MapLayerData::class)
        ->and($layerData->id)->toBe($layer->id)
        ->and($layerData->name)->toBe('Test Layer')
        ->and($layerData->type)->toBe(MapLayer::TYPE_RASTER)
        ->and($layerData->urlTemplate)->toBe('https://example.com/{z}/{x}/{y}.png')
        ->and($layerData->attribution)->toBe('Test Attribution')
        ->and($layerData->minZoom)->toBe(2)
        ->and($layerData->maxZoom)->toBe(18)
        ->and($layerData->opacity)->toBe(0.75)
        ->and($layerData->apiKey)->toBe('abc123')
        ->and($layerData->surfaces)->toBe(['pirep_view']);
});

it('omits disabled layers entirely', function (): void {
    MapLayer::factory()->disabled()->create(['name' => 'Hidden']);

    $config = app(MapConfigService::class)->resolve();

    expect($config->layers)->toBe([]);
});
