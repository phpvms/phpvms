<?php

declare(strict_types=1);

return [
    // Admin map layers resource
    'layer'                   => 'Map Layer',
    'layers'                  => 'Map Layers',
    'layer_name'              => 'Name',
    'layer_type'              => 'Type',
    'layer_type_hint'         => 'A WMS endpoint is a raster layer whose URL template carries the query string — there is no separate WMS type.',
    'layer_type_raster'       => 'Raster',
    'layer_type_vector'       => 'Vector',
    'layer_type_basemap'      => 'Basemap',
    'layer_url_template'      => 'URL Template',
    'layer_url_template_hint' => 'e.g. https://example.com/{z}/{x}/{y}.png, or a WMS URL with a {bbox-epsg-3857} placeholder',
    'layer_attribution'       => 'Attribution',
    'layer_attribution_hint'  => 'Required — the credit line shown to users. Most tile providers require this.',
    'layer_min_zoom'          => 'Min Zoom',
    'layer_max_zoom'          => 'Max Zoom',
    'layer_opacity'           => 'Opacity',
    'layer_api_key'           => 'API Key',
    'layer_api_key_hint'      => 'Not a secret — the browser fetches tiles directly, and any tile key is referrer-restricted by construction.',
    'layer_enabled'           => 'Enabled',

    // Basemap settings (Settings page, "map" group)
    'basemap_voyager'     => 'Carto Voyager (light)',
    'basemap_dark_matter' => 'Carto Dark Matter (dark)',
    'basemap_vacentral'   => 'vacentral',
    'basemap_esri'        => 'ESRI World Imagery (satellite)',
    'basemap_custom'      => 'Custom style…',

    'basemaps'                 => 'Base Maps',
    'basemaps_hint'            => 'The map style itself, swapped with the panel theme. Overlays are configured as layers in the table behind this drawer.',
    'basemaps_saved'           => 'Base maps saved',
    'basemap_light'            => 'Light style',
    'basemap_light_hint'       => 'Used when the panel is in light mode.',
    'basemap_dark'             => 'Dark style',
    'basemap_dark_hint'        => 'Used when the panel is in dark mode.',
    'basemap_custom_url_light' => 'Custom light style URL',
    'basemap_custom_url_dark'  => 'Custom dark style URL',

    'test_style'             => 'Test style',
    'test_style_success'     => 'Style loaded successfully',
    'test_style_failed'      => 'Style test failed',
    'test_style_no_url'      => 'Enter a custom style URL first.',
    'test_style_unreachable' => 'Could not reach that URL.',
    'test_style_bad_status'  => 'The server responded with status :status.',
    'test_style_invalid'     => 'That response is not a valid maplibre style document.',
];
