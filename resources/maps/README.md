# Static SVG maps

The world, six continents and every country as plain SVG, so a page can show positions without a map library.

## Source and licence

Natural Earth Admin 0 Countries, public domain (https://www.naturalearthdata.com/about/terms-of-use/). Countries and continents use the 1:50m scale, the world map uses 1:110m. The GeoJSON comes from https://github.com/nvkelso/natural-earth-vector (`geojson/ne_50m_admin_0_countries.geojson`, `geojson/ne_110m_admin_0_countries.geojson`).

## Regenerate

```sh
dir=$(mktemp -d /tmp/ne-maps.XXXXXX)
base=https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson
curl -sSL -o "$dir/ne_50m_admin_0_countries.geojson" $base/ne_50m_admin_0_countries.geojson
curl -sSL -o "$dir/ne_110m_admin_0_countries.geojson" $base/ne_110m_admin_0_countries.geojson
node bin/maps/build.mjs "$dir/ne_50m_admin_0_countries.geojson" "$dir/ne_110m_admin_0_countries.geojson" resources/maps
```

The script is plain Node with no dependencies. The generated files are committed.

## Files

- `world.svg`, `continents/{slug}.svg`, `countries/{iso2}.svg` (lowercase ISO 3166-1 alpha-2), `index.json`.
- Each SVG has a `viewBox` and one `<path class="land" data-iso="xx">` per country. `land--focus` marks the continent's own countries (continent maps) or the country itself (country maps). No fill or stroke is set; style it with CSS.
- Country and continent maps also draw the neighbours inside the view, without `land--focus`.
- A country can be split into regions in `COUNTRY_REGIONS` in `bin/maps/build.mjs`: an id, a name and lon/lat bounds each (`lonMin > lonMax` wraps the antimeridian). The first region is the main map (`countries/{iso2}.svg`) and the rest are `countries/{id}.svg` (the United States: `us` for the contiguous states, `us-ak`, `us-hi`). A polygon goes to the first region whose box holds the centre of its bounds; polygons in no region are left out. Region maps grey their neighbours like any country map.
- Countries with no unique ISO code (Somaliland, N. Cyprus, Indian Ocean Ter., Ashmore and Cartier Is., Siachen Glacier) are drawn without `data-iso` and have no country map. Antarctica has a country map but no continent map.

## index.json

Keyed by map id: `world`, `continent:europe`, `country:us`, `country:us-ak`. Each entry:

| Key                                    | Meaning                                                                                                                                        |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `type`                                 | `world`, `continent` or `country`                                                                                                              |
| `name`, `iso2`, `continent`            | display name; ISO code (countries only); continent slug                                                                                        |
| `width`, `height`                      | viewBox size (the longer side is 1000)                                                                                                         |
| `lonMin`, `lonMax`, `latMin`, `latMax` | view bounds in degrees, after the longitude shift                                                                                              |
| `k`, `cosLat0`                         | scale and cosine of the centre latitude                                                                                                        |
| `lonShift`, `lonWrap`                  | 360 or 0; when `lonShift` is set, a longitude below `lonWrap` gets `lonShift` added (antimeridian maps such as Russia, the USA, Fiji, Oceania) |
| `parent`                               | region maps only: the id (without `country:`) of the country's main map, for example `us`                                                      |
| `regions`                              | main maps of a country with regions only: the region ids, for example `["us-ak", "us-hi"]`                                                     |
| `aliases`                              | countries only: lowercase, accent-free names for matching free text                                                                            |

Projection (equirectangular, scaled by the centre latitude):

```
lon' = lon < lonWrap ? lon + lonShift : lon      (only when lonShift != 0)
x = (lon' - lonMin) * cosLat0 * k
y = (latMax - lat) * k
```

`App\Features\Maps\MapProjection` implements this.
