<?php

declare(strict_types=1);

/*
 * SPA-only (skylight theme) copy. Strings the Vue SPA renders that don't already
 * exist in a shared group. Consumed by laravel-vue-i18n via the Inertia-shared
 * message map (see HandleInertiaRequests::spaMessages). Reuse existing groups
 * (common, flights, ...) where a key already exists; add SPA-specific copy here.
 */

return [
    // Nav rail
    'brand_tagline' => 'Fleet Ops',
    'nav_section'   => 'Workspace',
    'nav_flights'   => 'Flights',
    'nav_bids'      => 'My Bids',
    'nav_logbook'   => 'Logbook',
    'on_duty'       => 'On duty',
    'role_pilot'    => 'Pilot',

    // Top bar
    'search'             => 'Search',
    'search_placeholder' => 'Search flights, airports, PIREPs…',
    'toggle_theme'       => 'Toggle theme',
    'notifications'      => 'Notifications',

    // Dashboard chrome
    'customize'          => 'Customize',
    'done'               => 'Done',
    'add_widget'         => 'Add widget',
    'reset'              => 'Reset',
    'all_widgets_placed' => 'All widgets placed',
    'on_leave'           => 'On leave',

    // Activity feed widget
    'activity'         => 'Activity',
    'flying_now'       => '{0}pilots flying now|{1}pilot flying now|[2,*]pilots flying now',
    'loading_activity' => 'Loading activity…',
    'activity_error'   => "Couldn't load the activity feed.",
    'no_activity'      => 'No activity yet',
];
