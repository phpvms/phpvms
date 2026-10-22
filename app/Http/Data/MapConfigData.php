<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Services\MapConfigService;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Map basemap + overlay configuration, resolved once by
 * {@see MapConfigService} and delivered through two
 * transports: Filament's `window.filamentData.maps` and Inertia shared
 * props (design.md D7 "one config DTO, two transports"). The package's
 * `createMap(el, { config, theme })` seam (D6) takes this shape directly.
 *
 * `layers` carries only *enabled* {@see MapLayerData} rows, in `order`.
 */
#[TypeScript]
final class MapConfigData extends Data
{
    /**
     * @param list<MapLayerData> $layers
     */
    public function __construct(
        public string $basemapLight,
        public string $basemapDark,
        public ?string $customStyleUrl,
        public ?string $customStyleApiKey,
        public array $layers,
    ) {}
}
