<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\MapLayer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The wire shape of one enabled overlay layer, nested in {@see MapConfigData}.
 *
 * `apiKey` is not a secret — see {@see MapLayer} — and is shipped to the
 * client plainly, the same way `openaip_api_key` is today.
 */
#[TypeScript]
final class MapLayerData extends Data
{
    /**
     * @param list<string>|null $surfaces
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public string $urlTemplate,
        public ?string $attribution,
        public int $minZoom,
        public int $maxZoom,
        public float $opacity,
        public ?string $apiKey,
        public ?array $surfaces,
    ) {}

    public static function fromModel(MapLayer $layer): self
    {
        return new self(
            id: $layer->id,
            name: $layer->name,
            type: $layer->type,
            urlTemplate: $layer->url_template,
            attribution: $layer->attribution,
            minZoom: $layer->min_zoom,
            maxZoom: $layer->max_zoom,
            opacity: $layer->opacity,
            apiKey: $layer->api_key,
            surfaces: $layer->surfaces,
        );
    }
}
