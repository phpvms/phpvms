<?php

declare(strict_types=1);

/*
 * Labels and descriptions of the core settings, by group and setting name.
 * See App\Support\Settings\SettingDefinition::label().
 */
return [
    'general' => [
        'site_name' => [
            'label'       => 'Site Name',
            'description' => 'The airline name shown across the admin panel and frontend',
        ],
        'theme' => [
            'label'       => 'Current Theme',
            'description' => 'The currently active theme',
        ],
        'start_date' => [
            'label'       => 'Start Date',
            'description' => 'The date your VA started',
        ],
        'admin_email' => [
            'label'       => 'Admin Email',
            'description' => 'Email where system notices, etc are sent',
        ],
        'check_prerelease_version' => [
            'label'       => 'Pre-release versions in version check',
            'description' => 'Include beta and other pre-release versions when checking for a new version',
        ],
        'telemetry' => [
            'label'       => 'Send telemetry to phpVMS',
            'description' => 'Send some data (php version, mysql version) to phpVMS. See AnalyticsSvc code for details',
        ],
        'google_analytics_id' => [
            'label'       => 'Google Analytics Tracking ID',
            'description' => 'Enter your Google Analytics Tracking ID',
        ],
        'record_user_ip' => [
            'label'       => 'Record user IP address',
            'description' => "Record the user's IP address on register/login",
        ],
        'invite_only_registrations' => [
            'label'       => 'Invite Only Registrations',
            'description' => 'If checked, only users with an invite can register',
        ],
        'disable_registrations' => [
            'label'       => 'Disable registrations',
            'description' => 'If checked, registrations will be disabled and only admins can add pilots',
        ],
        'auto_language_detection' => [
            'label'       => 'Auto language detection',
            'description' => "If checked, the app's language will be inferred from the browser's preferences",
        ],
    ],
    'captcha' => [
        'enabled' => [
            'label'       => 'hCaptcha Enabled',
            'description' => 'Is hCaptcha enabled',
        ],
        'site_key' => [
            'label'       => 'hCaptcha Site Key',
            'description' => 'Your hCaptcha Site Key',
        ],
        'secret_key' => [
            'label'       => 'hCaptcha Secret Key',
            'description' => 'Your hCaptcha Secret Key',
        ],
    ],
    'units' => [
        'currency' => [
            'label'       => 'Currency',
            'description' => 'The currency to use',
        ],
        'distance' => [
            'label'       => 'Distance Units',
            'description' => 'The distance unit for display',
        ],
        'weight' => [
            'label'       => 'Weight Units',
            'description' => 'The weight unit for display',
        ],
        'speed' => [
            'label'       => 'Speed Units',
            'description' => 'The speed unit for display',
        ],
        'altitude' => [
            'label'       => 'Altitude Units',
            'description' => 'The altitude unit for display',
        ],
        'fuel' => [
            'label'       => 'Fuel Units',
            'description' => 'The units for fuel for display',
        ],
        'volume' => [
            'label'       => 'Volume Units',
            'description' => 'The units of volume for display',
        ],
        'temperature' => [
            'label'       => 'Temperature Units',
            'description' => 'The units for temperature',
        ],
    ],
    'livemap' => [
        'live_time' => [
            'label'       => 'Completed Flight Time',
            'description' => 'How long a flight stays on the map after it stops being in progress, in minutes',
        ],
        'idle_time' => [
            'label'       => 'Stationary Flight Time',
            'description' => 'How long a flight that is not moving stays on the map, in minutes. Covers a paused flight and one that has been prefiled but has not yet departed',
        ],
        'center_coords' => [
            'label'       => 'Center Coords',
            'description' => 'Where to center the map; enter as LAT,LON',
        ],
        'default_zoom' => [
            'label'       => 'Default Zoom',
            'description' => 'Initial zoom level on the map',
        ],
        'update_interval' => [
            'label'       => 'Refresh Interval',
            'description' => 'How often the live map updates its data',
        ],
    ],
    'map' => [
        'custom_style_api_key' => [
            'label'       => 'Custom Style API Key',
            'description' => 'API key applied to the custom style URL tile requests, if it requires one',
        ],
    ],
    'airports' => [
        'auto_airport_lookup' => [
            'label'       => 'Automatic airport lookup',
            'description' => "If an airport isn't added, try to look it up when adding schedules",
        ],
        'allow_unadded_airports' => [
            'label'       => 'Allow unadded airports',
            'description' => 'If an un-added airport is used, it is looked up and added',
        ],
        'default_ground_handling_cost' => [
            'label'       => 'Default Ground Handling Cost',
            'description' => "If an airport's Ground Handling Cost isn't added, set this value by default",
        ],
        'default_jet_a_fuel_cost' => [
            'label'       => 'Default Jet A Fuel Cost',
            'description' => "If an airport's Jet A Fuel Cost isn't added, set this value by default",
        ],
        'default_100ll_fuel_cost' => [
            'label'       => 'Default 100LL Fuel Cost',
            'description' => "If an airport's 100LL Fuel Cost isn't added, set this value by default",
        ],
        'default_mogas_fuel_cost' => [
            'label'       => 'Default MOGAS Fuel Cost',
            'description' => "If an airport's MOGAS Fuel Cost isn't added, set this value by default",
        ],
    ],
    'bids' => [
        'disable_flight_on_bid' => [
            'label'       => 'Disable flight on bid',
            'description' => 'When a flight is bid on, no one else can bid on it',
        ],
        'allow_multiple_bids' => [
            'label'       => 'Allow multiple bids',
            'description' => 'Whether or not someone can bid on multiple flights',
        ],
        'expire_time' => [
            'label'       => 'Expire Time',
            'description' => 'Number of hours to expire bids after',
        ],
    ],
    'tours' => [
        'max_in_progress' => [
            'label'       => 'Max tours in progress',
            'description' => 'How many tours a pilot can have in progress at once. 0 for unlimited',
        ],
    ],
    'flights' => [
        'default_load_factor' => [
            'label'       => 'Load Factor',
            'description' => 'The default load factor for a flight, as a percent',
        ],
        'load_factor_variance' => [
            'label'       => 'Load Factor Variance',
            'description' => 'How much the load factor can vary per-flight',
        ],
        'use_cargo_load_factor' => [
            'label'       => 'Different Cargo Load Factor',
            'description' => 'When enabled these values will be used for cargo fares',
        ],
        'default_cargo_load_factor' => [
            'label'       => 'Cargo Load Factor',
            'description' => 'The default cargo load factor for a flight, as a percent',
        ],
        'cargo_load_factor_variance' => [
            'label'       => 'Cargo Load Factor Variance',
            'description' => 'How much the cargo load factor can vary per-flight',
        ],
    ],
    'operations' => [
        'restrict_aircraft_to_rank' => [
            'label'       => 'Restrict Aircraft to Ranks',
            'description' => "Aircraft restricted to user's rank",
        ],
        'restrict_aircraft_to_typerating' => [
            'label'       => 'Restrict Aircraft by Type Ratings',
            'description' => 'Aircraft restricted to user type ratings',
        ],
        'only_aircraft_at_dpt_airport' => [
            'label'       => 'Restrict Aircraft At Departure',
            'description' => 'Only allow aircraft that are at the departure airport',
        ],
        'only_company_aircraft' => [
            'label'       => 'Allow Only Company Aircraft',
            'description' => 'If no subfleets are assigned to a flight, only company aircraft will be used',
        ],
        'block_aircraft' => [
            'label'       => 'Lock aircraft while in use',
            'description' => 'When enabled, an aircraft can only be on one active bid, SimBrief OFP and flight/PIREP at a time',
        ],
        'advanced_fuel' => [
            'label'       => 'Advanced Fuel Calculations',
            'description' => 'Enables remaining fuel amounts to be considered for fuel expenses',
        ],
        'home_hubs_only' => [
            'label'       => 'Hubs as home airport',
            'description' => 'Pilots can only select hubs as their home airport',
        ],
        'only_flights_from_current' => [
            'label'       => 'Only allow flights from Current',
            'description' => 'Only allow flights from their current location',
        ],
        'only_show_flights_from_current' => [
            'label'       => 'Only show flights from Current',
            'description' => 'Only show flights from their current location',
        ],
    ],
    'simbrief' => [
        'api_key' => [
            'label'       => 'Simbrief API Key',
            'description' => 'Your Simbrief API key',
        ],
        'only_bids' => [
            'label'       => 'Only allow for bids',
            'description' => 'Only allow briefs to be created for bidded flights',
        ],
        'expire_hours' => [
            'label'       => 'Simbrief Expire Time',
            'description' => 'Hours after how long to remove unused briefs',
        ],
        'noncharter_pax_weight' => [
            'label'       => 'Non-Charter Passenger Weight',
            'description' => 'Passenger weight for non-charter flights excluding baggage (lbs)',
        ],
        'noncharter_baggage_weight' => [
            'label'       => 'Non-Charter Baggage Weight',
            'description' => 'Passenger baggage weight for non-charter flights (lbs)',
        ],
        'charter_pax_weight' => [
            'label'       => 'Charter Passenger Weight',
            'description' => 'Passenger weight for charter flights excluding baggage (lbs)',
        ],
        'charter_baggage_weight' => [
            'label'       => 'Charter Baggage Weight',
            'description' => 'Passenger baggage weight for charter flights (lbs)',
        ],
        'callsign' => [
            'label'       => 'Use ATC Callsign',
            'description' => 'Use pilot ident as Simbrief ATC Callsign',
        ],
        'name_private' => [
            'label'       => 'Use Privatized Name at OFPs',
            'description' => 'Use privatized user name as SimBrief OFP captain name',
        ],
        'use_standard_weights' => [
            'label'       => 'Use Only phpVMS Weights',
            'description' => 'When enabled, only phpVMS Passenger and Baggage weights will be used (instead of Airframe definitions)',
        ],
        'use_custom_airframes' => [
            'label'       => 'Use Only Custom Airframes',
            'description' => 'When enabled, only phpVMS Airframes will be listed for flight planning (instead of combined list)',
        ],
    ],
    'pireps' => [
        'disable_manual' => [
            'label'       => 'Disable Manual PIREPs',
            'description' => 'Prevent pilots from filing PIREPs through the frontend',
        ],
        'duplicate_check_time' => [
            'label'       => 'PIREP duplicate time check',
            'description' => 'The time in minutes to check for a duplicate PIREP',
        ],
        'tombstone_time' => [
            'label'       => 'Tombstone Time',
            'description' => 'How long an in-progress PIREP that has stopped reporting survives before it is cancelled, in hours. Set to 0 to never cancel a PIREP on account of age',
        ],
        'delete_cancelled_hours' => [
            'label'       => 'Delete cancelled PIREPs',
            'description' => 'The time in hours to delete a cancelled PIREP',
        ],
        'delete_rejected_hours' => [
            'label'       => 'Delete rejected PIREPs',
            'description' => 'The time in hours to delete a rejected PIREP',
        ],
        'handle_diversion' => [
            'label'       => 'Handle pirep diversion',
            'description' => 'Auto Handle Diversions (move assets and create re position flight)',
        ],
    ],
    'pilots' => [
        'id_length' => [
            'label'       => 'Pilot ID Length',
            'description' => "The length of a pilot's ID",
        ],
        'id_code' => [
            'label'       => 'Pilot ID Code',
            'description' => 'Fixed ICAO code for pilot IDs',
        ],
        'id_range_enabled' => [
            'label'       => 'Enable Pilot ID Range',
            'description' => 'Constrain newly assigned pilot IDs to a numbered range',
        ],
        'id_range_start' => [
            'label'       => 'Range Starting Number',
            'description' => 'The lowest pilot ID to assign when the range is enabled',
        ],
        'id_range_end' => [
            'label'       => 'Range Ending Number',
            'description' => 'The highest pilot ID to assign when the range is enabled',
        ],
        'id_fill_gaps' => [
            'label'       => 'Fill gaps in pilot IDs',
            'description' => 'Assign the lowest available pilot ID instead of always incrementing the highest',
        ],
        'id_reuse_deleted' => [
            'label'       => 'Re-use pilot IDs (from deleted users)',
            'description' => "Allow a deleted pilot's ID to be assigned to a new pilot",
        ],
        'auto_accept' => [
            'label'       => 'Auto Accept New Pilot',
            'description' => 'Automatically accept a pilot when they register',
        ],
        'auto_leave_days' => [
            'label'       => 'Pilot to ON LEAVE days',
            'description' => 'Automatically set a pilot to ON LEAVE status after N days of no activity',
        ],
        'hide_inactive' => [
            'label'       => 'Hide Inactive Pilots',
            'description' => "Don't show inactive pilots in the public view",
        ],
        'restrict_to_company' => [
            'label'       => 'Restrict the flights to company',
            'description' => "Restrict flights to the user's airline",
        ],
        'allow_transfer_hours' => [
            'label'       => 'Allow transfer hours',
            'description' => 'Allow specifying transfer hours on registration page and displayed on profile page',
        ],
        'count_transfer_hours' => [
            'label'       => 'Count transfer hours in calculations',
            'description' => 'Count transfer hours in calculations, like ranks and the total hours',
        ],
    ],
    'notifications' => [
        'discord_public_route' => [
            'label'       => 'Discord Public Route',
            'description' => 'Where public notifications are sent: either a Discord Webhook URL, or a channel ID to post through your bot (a channel ID requires DISCORD_BOT_TOKEN to be set)',
        ],
        'discord_private_route' => [
            'label'       => 'Discord Private Route',
            'description' => 'Where private (staff) notifications are sent: either a Discord Webhook URL, or a channel ID to post through your bot (a channel ID requires DISCORD_BOT_TOKEN to be set)',
        ],
        'discord_pirep_status' => [
            'label'       => 'Discord Pirep Messages (Public)',
            'description' => 'Pirep status messages (Only key events are being sent)',
        ],
        'mail_pirep_admin' => [
            'label'       => 'Pirep Filed (Admin)',
            'description' => 'Pirep filed mails sent to admins',
        ],
        'mail_pirep_user_ack' => [
            'label'       => 'Pirep Accepted (Pilot)',
            'description' => 'Pirep Accepted mails sent to pilots',
        ],
        'mail_pirep_user_rej' => [
            'label'       => 'Pirep Rejected (Pilot)',
            'description' => 'Pirep Rejected mails sent to pilots',
        ],
        'mail_news' => [
            'label'       => 'News Mails',
            'description' => 'News mails sent to all members',
        ],
        'discord_award_awarded' => [
            'label'       => 'Discord Award Message (Public)',
            'description' => 'Send out a discord notification when a user is awarded',
        ],
        'discord_user_rank_changed' => [
            'label'       => 'Discord User Rank Changed (Public)',
            'description' => "Send out a discord notification when a user's rank is changed",
        ],
        'discord_pirep_diverted' => [
            'label'       => 'Discord Pirep Diverted (Public)',
            'description' => 'Send out a discord notification when a pirep is diverted',
        ],
        'discord_pirep_filed' => [
            'label'       => 'Discord Pirep Filed Message (Public)',
            'description' => 'Send out a discord notification when a pirep is filed',
        ],
    ],
    'fares' => [
        'auto_price' => [
            'label'       => 'Automatic fare pricing',
            'description' => 'Compute PIREP fare prices from distance, seat category and airline type instead of using the configured fare/subfleet/flight prices',
        ],
        'low_cost_multiplier' => [
            'label'       => 'Low-cost airline multiplier',
            'description' => 'Multiplier applied to the automatic fare price when the PIREP airline is flagged as low cost',
        ],
    ],
    'cron' => [
        'random_id' => [
            'label'       => 'Cron Randomized ID',
            'description' => '',
        ],
    ],
    'registry' => [
        'va_global_id' => [
            'label'       => 'VA Global ID',
            'description' => '',
        ],
        'public_key' => [
            'label'       => 'Registry Public Key',
            'description' => '',
        ],
    ],
    'branding' => [
        'brand_color' => [
            'label'       => 'Brand Color',
            'description' => '',
        ],
    ],
];
