<?php

/**
 * Configuration for mapping URLs and stuff.
 */

return [
    /*
     * This can really be any METAR service, as long as it returns GeoJSON
     */
    'metar_wms' => [
        'url'    => 'https://ogcie.iblsoft.com/observations?',
        'params' => [
            'layers' => 'metar',
        ],
    ],
];
