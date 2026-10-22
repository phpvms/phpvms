<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Database\Factories\MapLayerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * An operator-configured map layer (design.md D9).
 *
 * `raster` and `vector` are OVERLAYS applied on top of the basemap, e.g.
 * OpenAIP airspace or a METAR WMS feed — WMS is not a separate type, it is a
 * raster layer whose `url_template` carries the query string.
 *
 * `basemap` is the map's own style rather than something drawn over it, so it
 * is deliberately NOT returned by {@see MapLayer::overlays()}: handing a
 * basemap row to the package's `applyLayers()` would try to add a style
 * document as a raster tile source. It is the only type that uses
 * `url_template_dark`, holding the dark-theme style alongside the light one
 * in `url_template`.
 *
 * `api_key` is not a secret: any tile key is referrer-restricted and public
 * by construction, since the browser is what fetches the tiles. It is stored
 * and shipped to the client in plain text; see design.md D9.
 *
 * @property int               $id
 * @property string            $name
 * @property string            $type
 * @property string            $url_template
 * @property string|null       $url_template_dark
 * @property string|null       $attribution
 * @property int               $min_zoom
 * @property int               $max_zoom
 * @property float             $opacity
 * @property bool              $enabled
 * @property int               $order
 * @property string|null       $api_key
 * @property list<string>|null $surfaces
 *
 * @method static MapLayerFactory factory($count = null, $state = [])
 */
class MapLayer extends Model
{
    /** @use HasFactory<MapLayerFactory> */
    use HasFactory;

    public const TYPE_RASTER = 'raster';

    public const TYPE_VECTOR = 'vector';

    public const TYPE_BASEMAP = 'basemap';

    public $table = 'map_layers';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'type',
        'url_template',
        'url_template_dark',
        'attribution',
        'min_zoom',
        'max_zoom',
        'opacity',
        'enabled',
        'order',
        'api_key',
        'surfaces',
    ];

    protected function casts(): array
    {
        return [
            'min_zoom' => 'integer',
            'max_zoom' => 'integer',
            'opacity'  => 'float',
            'enabled'  => 'boolean',
            'order'    => 'integer',
            'surfaces' => 'array',
        ];
    }

    /**
     * Enabled layers, applied order — the shared base for the two scopes below.
     *
     * @param  Builder<MapLayer> $query
     * @return Builder<MapLayer>
     */
    #[Scope]
    protected function enabledOrdered(Builder $query): Builder
    {
        return $query->where('enabled', true)->orderBy('order');
    }

    /**
     * Enabled OVERLAY layers — the set every map surface draws on top of its
     * basemap. Excludes `basemap` rows on purpose: they are the style the map
     * is built from, not something layered over it, and `applyLayers()` would
     * try to add one as a tile source.
     *
     * @param  Builder<MapLayer> $query
     * @return Builder<MapLayer>
     */
    #[Scope]
    protected function overlays(Builder $query): Builder
    {
        return $query->enabledOrdered()->where('type', '!=', self::TYPE_BASEMAP);
    }

    /**
     * The active basemap: the first enabled `basemap` row in applied order.
     * More than one may exist so an operator can keep alternatives around;
     * order decides which is live.
     *
     * @param  Builder<MapLayer> $query
     * @return Builder<MapLayer>
     */
    #[Scope]
    protected function activeBasemap(Builder $query): Builder
    {
        return $query->enabledOrdered()->where('type', self::TYPE_BASEMAP);
    }
}
