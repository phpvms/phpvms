<?php

declare(strict_types=1);

return [
    // Base URL of the addon registry API.
    'registry_url' => env('ADDON_REGISTRY_URL', 'https://registry.phpvms.net'),

    // How long (seconds) to cache the fetched catalog before a background
    // refresh. Default 6 hours.
    'catalog_ttl' => (int) env('ADDON_REGISTRY_CATALOG_TTL', 6 * 60 * 60),

    // How often the scheduled update check runs. One of: hourly, sixhourly,
    // daily. Default daily.
    'update_check_cadence' => env('ADDON_REGISTRY_UPDATE_CADENCE', 'daily'),

    // HTTP timeout (seconds) for registry requests.
    'http_timeout' => (int) env('ADDON_REGISTRY_HTTP_TIMEOUT', 20),

    // Cache store for the registry catalog / release metadata / install progress.
    // These MUST persist across requests — the whole point of the catalog cache
    // is to avoid a network fetch on every page interaction. When unset, the app
    // default is used, unless that default is the non-persistent `array` driver
    // (common in dev), in which case we fall back to `file` so the feature stays
    // fast instead of re-fetching on every Livewire update.
    'cache_store' => env('ADDON_REGISTRY_CACHE_STORE'),
];
