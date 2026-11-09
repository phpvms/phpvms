<?php

declare(strict_types=1);

/*
 * SPA UI copy — strings the Vue frontend renders that don't already exist in a
 * shared group. Theme-agnostic group name (`ui`), not tied to any one theme.
 * Consumed by laravel-vue-i18n via the Inertia-shared message map (see
 * HandleInertiaRequests::spaMessages). Reuse existing groups (common, flights,
 * ...) where a key already exists; add SPA-specific copy here.
 */

return [
    // Nav rail
    'brand_tagline' => 'Fleet Ops',
    'nav_flights'   => 'Flights',
    'nav_bids'      => 'My Bids',
    'nav_tours'     => 'Tours',
    'nav_logbook'   => 'Logbook',
    'on_duty'       => 'On duty',
    'role_pilot'    => 'Pilot',

    // Top bar
    'search'             => 'Search',
    'search_placeholder' => 'Search flights, airports, PIREPs…',
    'toggle_theme'       => 'Toggle theme',
    'notifications'      => 'Notifications',

    // Sidebar account menu
    'go_to_admin' => 'Go to Admin',
    'sign_in'     => 'Sign in',
    'sign_out'    => 'Sign out',
    'theme'       => 'Theme',
    'theme_light' => 'Light',
    'theme_dark'  => 'Dark',
    'theme_auto'  => 'Auto',

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

    // Latest news widget
    'latest_news' => 'Latest news',
    'no_news'     => 'No news yet',

    // Next flight widget
    'next_flight'           => 'Next flight',
    'ready_to_fly'          => 'Ready to fly',
    'last_flight_title'     => 'Last flight',
    'find_a_flight'         => 'Find a flight',
    'start_flight_planning' => 'Start flight planning',
    'ofp_ready'             => 'OFP ready',
    'ofp_not_planned'       => 'Not planned',
];
