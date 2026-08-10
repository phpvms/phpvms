<?php

declare(strict_types=1);

return [
    // Tabs and list controls
    'browse_registry' => 'Browse registry',
    'updates'         => 'Updates',
    'installed_tab'   => 'Installed',
    'registry_tab'    => 'Registry',
    'all_categories'  => 'All categories',
    'search_addons'   => 'Search :count add-ons by name or publisher…',
    'no_addons_found' => 'No addons found.',
    'select_an_addon' => 'Select an addon to see its details.',

    // Enable-state tabs within the active tab
    'state_all'      => 'All',
    'state_enabled'  => 'Enabled',
    'state_disabled' => 'Disabled',

    // Page header
    'heading'               => 'Add-ons',
    'metric_listed'         => 'Listed',
    'metric_updates'        => 'Update available|Updates available',
    'metric_disabled'       => 'Disabled',
    'check_updates'         => 'Check for updates',
    'no_results_title'      => 'Nothing matches those filters',
    'no_results_hint'       => 'Clear the search or pick a different category to see the rest of the catalog.',
    'nothing_selected'      => 'Pick an add-on',
    'nothing_selected_hint' => 'Choose one from the list to see what it requires, what it changed and where it came from.',

    // Detail register
    'official'         => 'Official',
    'official_hint'    => 'Official add-on',
    'website'          => 'Website',
    'changelog'        => 'Changelog',
    'changelog_inline' => 'opens in a panel',
    'compatible'       => 'Compatible',
    'version'          => 'Version',
    'released'         => 'Released',
    // Register labels. The lowercase `installed`/`latest`/`requires` keys
    // above are inline words in sentences; these are column headings.
    'label_installed' => 'Installed',
    'label_latest'    => 'Latest',
    'label_requires'  => 'Requires',
    'label_size'      => 'Size',
    'enable'          => 'Enable',
    'disable'         => 'Disable',
    'remove'          => 'Remove',
    'verify_note'     => "Downloads are checked against the publisher's SHA-256 and a signature from :host before anything is written to disk.",
    'showing_range'   => 'Showing :from–:to of :total',
    'previous'        => 'Previous',
    'next'            => 'Next',

    // Sync status
    'not_synced'     => 'Registry not synced yet',
    'synced_when'    => 'Registry synced :when',
    'showing_cached' => 'showing cached',
    'refresh'        => 'refresh',

    // Row and detail facts
    'by'               => 'by',
    'by_publisher'     => 'by :publisher',
    'installs'         => 'installs',
    'update_available' => 'update available',
    'installed'        => 'installed',
    'disabled'         => 'disabled',
    'bundled'          => 'bundled with phpVMS',
    'bundled_short'    => 'Bundled',
    'requires'         => 'requires',
    'latest'           => 'latest',
    'channel'          => 'channel',
    'releases'         => 'Releases',
    'repository'       => 'repository',
    'verified_note'    => 'sha256 verified · signed by :host',

    // Actions, modal, notifications
    'install'                => 'Install',
    'update_to'              => 'Update to v:version',
    'install_name'           => 'Install :name',
    'upload_zip'             => 'Upload .zip',
    'addon_package'          => 'Addon package',
    'run_migrations'         => 'Run database migrations after install',
    'requires_ok'            => 'requires :req ✓',
    'size'                   => 'size :size',
    'verified_download'      => 'The download is verified by registry signature and sha256.',
    'addon_installed'        => 'Addon installed',
    'install_failed'         => 'Install failed',
    'install_queued'         => 'Install queued',
    'install_running_bg'     => 'The install is running in the background.',
    'queued'                 => 'Queued…',
    'catalog_refreshed'      => 'Catalog refreshed',
    'registry_unreachable'   => 'Could not reach the registry',
    'showing_cached_catalog' => 'Showing the last cached catalog.',
];
