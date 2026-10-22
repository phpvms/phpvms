<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Http\Data\MapConfigData;
use App\Http\Data\MapLayerData;
use App\Models\MapLayer;

/**
 * Resolves the enabled `map_layers` rows — the active `basemap` row plus every
 * overlay — into one {@see MapConfigData}. The basemap used to live in the
 * `map` settings group; it moved into `map_layers` so the whole map
 * configuration is edited in one admin screen.
 *
 * One resolver serving two transports — Filament's `window.filamentData.maps`
 * and Inertia shared props — per design.md D7; callers must not grow a second
 * resolution path for either transport.
 */
class MapConfigService extends Service
{
    /**
     * Build the current map configuration for delivery to a client.
     */
    public function resolve(): MapConfigData
    {
        $basemap = MapLayer::query()->activeBasemap()->first();

        // An explicit instanceof rather than `?->` chains: the columns
        // themselves are non-nullable strings, so phpstan reads a nullsafe on
        // the left of `??` as redundant. A missing basemap row is a real state
        // (a fresh install before the drawer is ever opened), and the package
        // treats an empty style URL as "not configured".
        $light = $basemap instanceof MapLayer ? $basemap->url_template : '';
        $dark = $basemap instanceof MapLayer
            ? ($basemap->url_template_dark ?? $basemap->url_template)
            : '';

        return new MapConfigData(
            basemapLight: $light,
            basemapDark: $dark,
            customStyleUrl: $this->nullableSetting('map.custom_style_url'),
            customStyleApiKey: $this->nullableSetting('map.custom_style_api_key'),
            layers: $this->resolveLayers(),
        );
    }

    /**
     * OVERLAYS only — `basemap` rows are resolved into `basemapLight`/
     * `basemapDark` above, and handing one to the package's `applyLayers()`
     * would try to add a style document as a tile source.
     *
     * @return list<MapLayerData>
     */
    private function resolveLayers(): array
    {
        return MapLayer::query()
            ->overlays()
            ->get()
            ->map(fn (MapLayer $layer): MapLayerData => MapLayerData::fromModel($layer))
            ->values()
            ->all();
    }

    /**
     * A setting stored as an empty string reads back as "unset" here — every
     * `map.*` text setting is optional, and the DTO's nullable fields exist so
     * a caller can tell "not configured" from "configured to an empty style",
     * which maplibre would reject anyway.
     */
    private function nullableSetting(string $key): ?string
    {
        $value = (string) setting($key, '');

        return $value === '' ? null : $value;
    }
}
