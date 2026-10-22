<?php

declare(strict_types=1);

use App\Http\Data\MapConfigData;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\MapLayer;
use Illuminate\Http\Request;

/**
 * design.md D7 "one config DTO, two transports" — the skylight side. One
 * resolver (MapConfigService) shared with AdminPanelProvider's
 * window.filamentData.maps; see MapConfigSharedPropsTest's admin sibling,
 * AdminMapConfigDataTest.
 */
it('shares the current map config lazily as MapConfigData', function (): void {
    MapLayer::factory()->basemap()->create([
        'url_template'      => 'https://example.com/light.json',
        'url_template_dark' => 'https://example.com/dark.json',
    ]);
    MapLayer::factory()->create(['name' => 'Test Overlay', 'enabled' => true, 'order' => 0]);
    MapLayer::factory()->disabled()->create(['name' => 'Hidden Overlay']);

    $shared = mapSharedPropsFor();

    expect($shared['map'])->toBeInstanceOf(Closure::class);

    $map = ($shared['map'])();

    expect($map)->toBeInstanceOf(MapConfigData::class)
        ->and($map->basemapLight)->toBe('https://example.com/light.json')
        ->and($map->basemapDark)->toBe('https://example.com/dark.json')
        ->and($map->layers)->toHaveCount(1)
        ->and($map->layers[0]->name)->toBe('Test Overlay');
});

/** @return array<string, mixed> */
function mapSharedPropsFor(): array
{
    $request = Request::create('/dashboard');
    $request->setUserResolver(fn (): ?object => null);

    return app(HandleInertiaRequests::class)->share($request);
}
