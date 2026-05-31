<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class SettingsSeeder extends Seeder
{
    /**
     * Default setting definitions. On re-seed, `value` is never overwritten
     * (only inserted for new rows) so user changes are preserved.
     *
     * @var list<array{key: string, name: string, value: string, group: string, type: string, options: string, description: string}>
     */
    private array $settings = [
        // General
        [
            'key'         => 'general.theme',
            'name'        => 'Current Theme',
            'group'       => 'general',
            'value'       => 'seven',
            'type'        => 'select',
            'options'     => '',
            'description' => 'The currently active theme',
        ],
        [
            'key'         => 'general.start_date',
            'name'        => 'Start Date',
            'group'       => 'general',
            'value'       => '',
            'type'        => 'date',
            'options'     => '',
            'description' => 'The date your VA started',
        ],
        [
            'key'         => 'general.admin_email',
            'name'        => 'Admin Email',
            'group'       => 'general',
            'value'       => '',
            'type'        => 'text',
            'options'     => '',
            'description' => 'Email where system notices, etc are sent',
        ],
        [
            'key'         => 'general.auto_airport_lookup',
            'name'        => 'Automatic airport lookup',
            'group'       => 'general',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => "If an airport isn't added, try to look it up when adding schedules",
        ],
        [
            'key'         => 'general.allow_unadded_airports',
            'name'        => 'Allow unadded airports',
            'group'       => 'general',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'If an un-added airport is used, it is looked up and added',
        ],
        [
            'key'         => 'general.check_prerelease_version',
            'name'        => 'Pre-release versions in version check',
            'group'       => 'general',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Include beta and other pre-release versions when checking for a new version',
        ],
        [
            'key'         => 'general.telemetry',
            'name'        => 'Send telemetry to phpVMS',
            'group'       => 'general',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Send some data (php version, mysql version) to phpVMS. See AnalyticsSvc code for details',
        ],
        [
            'key'         => 'general.google_analytics_id',
            'name'        => 'Google Analytics Tracking ID',
            'group'       => 'general',
            'value'       => '',
            'type'        => 'text',
            'options'     => '',
            'description' => 'Enter your Google Analytics Tracking ID',
        ],
        [
            'key'         => 'general.record_user_ip',
            'name'        => 'Record user IP address',
            'group'       => 'general',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => "Record the user's IP address on register/login",
        ],
        [
            'key'         => 'general.invite_only_registrations',
            'name'        => 'Invite Only Registrations',
            'group'       => 'general',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'If checked, only users with an invite can register',
        ],
        [
            'key'         => 'general.disable_registrations',
            'name'        => 'Disable registrations',
            'group'       => 'general',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'If checked, registrations will be disabled and only admins can add pilots',
        ],
        [
            'key'         => 'general.auto_language_detection',
            'name'        => 'Auto language detection',
            'group'       => 'general',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => "If checked, the app's language will be inferred from the browser's preferences",
        ],

        // Captcha
        [
            'key'         => 'captcha.enabled',
            'name'        => 'hCaptcha Enabled',
            'group'       => 'captcha',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Is hCaptcha enabled',
        ],
        [
            'key'         => 'captcha.site_key',
            'name'        => 'hCaptcha Site Key',
            'group'       => 'captcha',
            'value'       => '',
            'type'        => 'text',
            'options'     => '',
            'description' => 'Your hCaptcha Site Key',
        ],
        [
            'key'         => 'captcha.secret_key',
            'name'        => 'hCaptcha Secret Key',
            'group'       => 'captcha',
            'value'       => '',
            'type'        => 'text',
            'options'     => '',
            'description' => 'Your hCaptcha Secret Key',
        ],

        // Units
        [
            'key'         => 'units.currency',
            'name'        => 'Currency',
            'group'       => 'units',
            'value'       => 'USD',
            'type'        => 'select',
            'options'     => '',
            'description' => 'The currency to use',
        ],
        [
            'key'         => 'units.distance',
            'name'        => 'Distance Units',
            'group'       => 'units',
            'value'       => 'nmi',
            'type'        => 'select',
            'options'     => 'km=kilometers,mi=miles,nmi=nautical miles',
            'description' => 'The distance unit for display',
        ],
        [
            'key'         => 'units.weight',
            'name'        => 'Weight Units',
            'group'       => 'units',
            'value'       => 'lbs',
            'type'        => 'select',
            'options'     => 'lbs,kg',
            'description' => 'The weight unit for display',
        ],
        [
            'key'         => 'units.speed',
            'name'        => 'Speed Units',
            'group'       => 'units',
            'value'       => 'knot',
            'type'        => 'select',
            'options'     => 'km/h,knot',
            'description' => 'The speed unit for display',
        ],
        [
            'key'         => 'units.altitude',
            'name'        => 'Altitude Units',
            'group'       => 'units',
            'value'       => 'ft',
            'type'        => 'select',
            'options'     => 'ft=feet,m=meters',
            'description' => 'The altitude unit for display',
        ],
        [
            'key'         => 'units.fuel',
            'name'        => 'Fuel Units',
            'group'       => 'units',
            'value'       => 'lbs',
            'type'        => 'select',
            'options'     => 'lbs,kg',
            'description' => 'The units for fuel for display',
        ],
        [
            'key'         => 'units.volume',
            'name'        => 'Volume Units',
            'group'       => 'units',
            'value'       => 'gallons',
            'type'        => 'select',
            'options'     => 'gallons,l=liters',
            'description' => 'The units of volume for display',
        ],
        [
            'key'         => 'units.temperature',
            'name'        => 'Temperature Units',
            'group'       => 'units',
            'value'       => 'F',
            'type'        => 'select',
            'options'     => 'F=Fahrenheit,C=Celsius',
            'description' => 'The units for temperature',
        ],

        // ACARS
        [
            'key'         => 'acars.live_time',
            'name'        => 'Live Time',
            'group'       => 'acars',
            'value'       => '12',
            'type'        => 'int',
            'options'     => '',
            'description' => 'Age of flights to show on the map in hours. Set to 0 to show only all in-progress flights',
        ],
        [
            'key'         => 'acars.center_coords',
            'name'        => 'Center Coords',
            'group'       => 'acars',
            'value'       => '30.1945,-97.6699',
            'type'        => 'text',
            'options'     => '',
            'description' => 'Where to center the map; enter as LAT,LON',
        ],
        [
            'key'         => 'acars.default_zoom',
            'name'        => 'Default Zoom',
            'group'       => 'acars',
            'value'       => '5',
            'type'        => 'int',
            'options'     => '',
            'description' => 'Initial zoom level on the map',
        ],
        [
            'key'         => 'acars.update_interval',
            'name'        => 'Refresh Interval',
            'group'       => 'acars',
            'value'       => '60',
            'type'        => 'int',
            'options'     => '',
            'description' => 'How often the live map updates its data',
        ],

        // Airports
        [
            'key'         => 'airports.default_ground_handling_cost',
            'name'        => 'Default Ground Handling Cost',
            'group'       => 'airports',
            'value'       => '250',
            'type'        => 'int',
            'options'     => '',
            'description' => "If an airport's Ground Handling Cost isn't added, set this value by default",
        ],
        [
            'key'         => 'airports.default_jet_a_fuel_cost',
            'name'        => 'Default Jet A Fuel Cost',
            'group'       => 'airports',
            'value'       => '0.7',
            'type'        => 'text',
            'options'     => '',
            'description' => "If an airport's Jet A Fuel Cost isn't added, set this value by default",
        ],
        [
            'key'         => 'airports.default_100ll_fuel_cost',
            'name'        => 'Default 100LL Fuel Cost',
            'group'       => 'airports',
            'value'       => '0.9',
            'type'        => 'text',
            'options'     => '',
            'description' => "If an airport's 100LL Fuel Cost isn't added, set this value by default",
        ],
        [
            'key'         => 'airports.default_mogas_fuel_cost',
            'name'        => 'Default MOGAS Fuel Cost',
            'group'       => 'airports',
            'value'       => '0.8',
            'type'        => 'text',
            'options'     => '',
            'description' => "If an airport's MOGAS Fuel Cost isn't added, set this value by default",
        ],

        // Bids
        [
            'key'         => 'bids.disable_flight_on_bid',
            'name'        => 'Disable flight on bid',
            'group'       => 'bids',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'When a flight is bid on, no one else can bid on it',
        ],
        [
            'key'         => 'bids.allow_multiple_bids',
            'name'        => 'Allow multiple bids',
            'group'       => 'bids',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Whether or not someone can bid on multiple flights',
        ],
        [
            'key'         => 'bids.block_aircraft',
            'name'        => 'Restrict Aircraft',
            'group'       => 'bids',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'When enabled, an aircraft can only be used for one active Bid and Flight/Pirep',
        ],
        [
            'key'         => 'bids.expire_time',
            'name'        => 'Expire Time',
            'group'       => 'bids',
            'value'       => '48',
            'type'        => 'int',
            'options'     => '',
            'description' => 'Number of hours to expire bids after',
        ],

        // Flights
        [
            'key'         => 'flights.default_load_factor',
            'name'        => 'Load Factor',
            'group'       => 'flights',
            'value'       => '82',
            'type'        => 'number',
            'options'     => '',
            'description' => 'The default load factor for a flight, as a percent',
        ],
        [
            'key'         => 'flights.load_factor_variance',
            'name'        => 'Load Factor Variance',
            'group'       => 'flights',
            'value'       => '5',
            'type'        => 'number',
            'options'     => '',
            'description' => 'How much the load factor can vary per-flight',
        ],
        [
            'key'         => 'flights.use_cargo_load_factor',
            'name'        => 'Different Cargo Load Factor',
            'group'       => 'flights',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'When enabled these values will be used for cargo fares',
        ],
        [
            'key'         => 'flights.default_cargo_load_factor',
            'name'        => 'Cargo Load Factor',
            'group'       => 'flights',
            'value'       => '32',
            'type'        => 'number',
            'options'     => '',
            'description' => 'The default cargo load factor for a flight, as a percent',
        ],
        [
            'key'         => 'flights.cargo_load_factor_variance',
            'name'        => 'Cargo Load Factor Variance',
            'group'       => 'flights',
            'value'       => '5',
            'type'        => 'number',
            'options'     => '',
            'description' => 'How much the cargo load factor can vary per-flight',
        ],
        [
            'key'         => 'flights.only_company_aircraft',
            'name'        => 'Allow Only Company Aircraft',
            'group'       => 'flights',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'If no subfleets are assigned to a flight, only company aircraft will be used',
        ],

        // SimBrief
        [
            'key'         => 'simbrief.api_key',
            'name'        => 'Simbrief API Key',
            'group'       => 'simbrief',
            'value'       => '',
            'type'        => 'string',
            'options'     => '',
            'description' => 'Your Simbrief API key',
        ],
        [
            'key'         => 'simbrief.only_bids',
            'name'        => 'Only allow for bids',
            'group'       => 'simbrief',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Only allow briefs to be created for bidded flights',
        ],
        [
            'key'         => 'simbrief.expire_hours',
            'name'        => 'Simbrief Expire Time',
            'group'       => 'simbrief',
            'value'       => '6',
            'type'        => 'number',
            'options'     => '',
            'description' => 'Hours after how long to remove unused briefs',
        ],
        [
            'key'         => 'simbrief.noncharter_pax_weight',
            'name'        => 'Non-Charter Passenger Weight',
            'group'       => 'simbrief',
            'value'       => '185',
            'type'        => 'number',
            'options'     => '',
            'description' => 'Passenger weight for non-charter flights excluding baggage (lbs)',
        ],
        [
            'key'         => 'simbrief.noncharter_baggage_weight',
            'name'        => 'Non-Charter Baggage Weight',
            'group'       => 'simbrief',
            'value'       => '35',
            'type'        => 'number',
            'options'     => '',
            'description' => 'Passenger baggage weight for non-charter flights (lbs)',
        ],
        [
            'key'         => 'simbrief.charter_pax_weight',
            'name'        => 'Charter Passenger Weight',
            'group'       => 'simbrief',
            'value'       => '168',
            'type'        => 'number',
            'options'     => '',
            'description' => 'Passenger weight for charter flights excluding baggage (lbs)',
        ],
        [
            'key'         => 'simbrief.charter_baggage_weight',
            'name'        => 'Charter Baggage Weight',
            'group'       => 'simbrief',
            'value'       => '28',
            'type'        => 'number',
            'options'     => '',
            'description' => 'Passenger baggage weight for charter flights (lbs)',
        ],
        [
            'key'         => 'simbrief.callsign',
            'name'        => 'Use ATC Callsign',
            'group'       => 'simbrief',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Use pilot ident as Simbrief ATC Callsign',
        ],
        [
            'key'         => 'simbrief.name_private',
            'name'        => 'Use Privatized Name at OFPs',
            'group'       => 'simbrief',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Use privatized user name as SimBrief OFP captain name',
        ],
        [
            'key'         => 'simbrief.block_aircraft',
            'name'        => 'Restrict Aircraft',
            'group'       => 'simbrief',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'When enabled, an aircraft can only be used for one active SimBrief OFP and Flight/Pirep',
        ],
        [
            'key'         => 'simbrief.use_standard_weights',
            'name'        => 'Use Only phpVMS Weights',
            'group'       => 'simbrief',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'When enabled, only phpVMS Passenger and Baggage weights will be used (instead of Airframe definitions)',
        ],
        [
            'key'         => 'simbrief.use_custom_airframes',
            'name'        => 'Use Only Custom Airframes',
            'group'       => 'simbrief',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'When enabled, only phpVMS Airframes will be listed for flight planning (instead of combined list)',
        ],

        // PIREPs
        [
            'key'         => 'pireps.duplicate_check_time',
            'name'        => 'PIREP duplicate time check',
            'group'       => 'pireps',
            'value'       => '10',
            'type'        => 'int',
            'options'     => '',
            'description' => 'The time in minutes to check for a duplicate PIREP',
        ],
        [
            'key'         => 'pireps.restrict_aircraft_to_rank',
            'name'        => 'Restrict Aircraft to Ranks',
            'group'       => 'pireps',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => "Aircraft restricted to user's rank",
        ],
        [
            'key'         => 'pireps.restrict_aircraft_to_typerating',
            'name'        => 'Restrict Aircraft by Type Ratings',
            'group'       => 'pireps',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Aircraft restricted to user type ratings',
        ],
        [
            'key'         => 'pireps.only_aircraft_at_dpt_airport',
            'name'        => 'Restrict Aircraft At Departure',
            'group'       => 'pireps',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Only allow aircraft that are at the departure airport',
        ],
        [
            'key'         => 'pireps.advanced_fuel',
            'name'        => 'Advanced Fuel Calculations',
            'group'       => 'pireps',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Enables remaining fuel amounts to be considered for fuel expenses',
        ],
        [
            'key'         => 'pireps.delete_cancelled_hours',
            'name'        => 'Delete cancelled PIREPs',
            'group'       => 'pireps',
            'value'       => '12',
            'type'        => 'int',
            'options'     => '',
            'description' => 'The time in hours to delete a cancelled PIREP',
        ],
        [
            'key'         => 'pireps.delete_rejected_hours',
            'name'        => 'Delete rejected PIREPs',
            'group'       => 'pireps',
            'value'       => '12',
            'type'        => 'int',
            'options'     => '',
            'description' => 'The time in hours to delete a rejected PIREP',
        ],
        [
            'key'         => 'pireps.handle_diversion',
            'name'        => 'Handle pirep diversion',
            'group'       => 'pireps',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Auto Handle Diversions (move assets and create re position flight)',
        ],

        // Pilots
        [
            'key'         => 'pilots.id_length',
            'name'        => 'Pilot ID Length',
            'group'       => 'pilots',
            'value'       => '4',
            'type'        => 'int',
            'options'     => '',
            'description' => "The length of a pilot's ID",
        ],
        [
            'key'         => 'pilots.id_code',
            'name'        => 'Pilot ID Code',
            'group'       => 'pilots',
            'value'       => '',
            'type'        => 'text',
            'options'     => '',
            'description' => 'Fixed ICAO code for pilot IDs',
        ],
        [
            'key'         => 'pilots.auto_accept',
            'name'        => 'Auto Accept New Pilot',
            'group'       => 'pilots',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Automatically accept a pilot when they register',
        ],
        [
            'key'         => 'pilots.home_hubs_only',
            'name'        => 'Hubs as home airport',
            'group'       => 'pilots',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Pilots can only select hubs as their home airport',
        ],
        [
            'key'         => 'pilots.only_flights_from_current',
            'name'        => 'Only allow flights from Current',
            'group'       => 'pilots',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Only allow flights from their current location',
        ],
        [
            'key'         => 'pilots.only_show_flights_from_current',
            'name'        => 'Only show flights from Current',
            'group'       => 'pilots',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Only show flights from their current location',
        ],
        [
            'key'         => 'pilots.auto_leave_days',
            'name'        => 'Pilot to ON LEAVE days',
            'group'       => 'pilots',
            'value'       => '30',
            'type'        => 'int',
            'options'     => '',
            'description' => 'Automatically set a pilot to ON LEAVE status after N days of no activity',
        ],
        [
            'key'         => 'pilots.hide_inactive',
            'name'        => 'Hide Inactive Pilots',
            'group'       => 'pilots',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => "Don't show inactive pilots in the public view",
        ],
        [
            'key'         => 'pilots.restrict_to_company',
            'name'        => 'Restrict the flights to company',
            'group'       => 'pilots',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => "Restrict flights to the user's airline",
        ],
        [
            'key'         => 'pilots.allow_transfer_hours',
            'name'        => 'Allow transfer hours',
            'group'       => 'pilots',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Allow specifying transfer hours on registration page and displayed on profile page',
        ],
        [
            'key'         => 'pilots.count_transfer_hours',
            'name'        => 'Count transfer hours in calculations',
            'group'       => 'pilots',
            'value'       => 'false',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Count transfer hours in calculations, like ranks and the total hours',
        ],

        // Notifications
        [
            'key'         => 'notifications.discord_public_webhook_url',
            'name'        => 'Discord Public Webhook URL',
            'group'       => 'notifications',
            'value'       => '',
            'type'        => 'text',
            'options'     => '',
            'description' => 'The Discord Webhook URL for public notifications',
        ],
        [
            'key'         => 'notifications.discord_private_webhook_url',
            'name'        => 'Discord Private Webhook URL',
            'group'       => 'notifications',
            'value'       => '',
            'type'        => 'text',
            'options'     => '',
            'description' => 'The Discord Webhook URL for private notifications',
        ],
        [
            'key'         => 'notifications.discord_pirep_status',
            'name'        => 'Discord Pirep Messages (Public)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Pirep status messages (Only key events are being sent)',
        ],
        [
            'key'         => 'notifications.mail_pirep_admin',
            'name'        => 'Pirep Filed (Admin)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Pirep filed mails sent to admins',
        ],
        [
            'key'         => 'notifications.mail_pirep_user_ack',
            'name'        => 'Pirep Accepted (Pilot)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Pirep Accepted mails sent to pilots',
        ],
        [
            'key'         => 'notifications.mail_pirep_user_rej',
            'name'        => 'Pirep Rejected (Pilot)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Pirep Rejected mails sent to pilots',
        ],
        [
            'key'         => 'notifications.mail_news',
            'name'        => 'News Mails',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'News mails sent to all members',
        ],
        [
            'key'         => 'notifications.discord_award_awarded',
            'name'        => 'Discord Award Message (Public)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Send out a discord notification when a user is awarded',
        ],
        [
            'key'         => 'notifications.discord_user_rank_changed',
            'name'        => 'Discord User Rank Changed (Public)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => "Send out a discord notification when a user's rank is changed",
        ],
        [
            'key'         => 'notifications.discord_pirep_diverted',
            'name'        => 'Discord Pirep Diverted (Public)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Send out a discord notification when a pirep is diverted',
        ],
        [
            'key'         => 'notifications.discord_pirep_filed',
            'name'        => 'Discord Pirep Filed Message (Public)',
            'group'       => 'notifications',
            'value'       => 'true',
            'type'        => 'boolean',
            'options'     => 'true,false',
            'description' => 'Send out a discord notification when a pirep is filed',
        ],

        // Cron
        [
            'key'         => 'cron.random_id',
            'name'        => 'Cron Randomized ID',
            'group'       => 'cron',
            'value'       => '',
            'type'        => 'hidden',
            'options'     => '',
            'description' => '',
        ],
    ];

    public function run(): void
    {
        $groupOffsets = [];
        $groupOrders = [];
        $rows = [];

        foreach ($this->settings as $setting) {
            $group = $setting['group'];

            if (!isset($groupOffsets[$group])) {
                $groupOffsets[$group] = 0;
                $groupOrders[$group] = 0;
            }

            $rows[] = [
                'id'          => Setting::formatKey($setting['key']),
                'key'         => $setting['key'],
                'name'        => $setting['name'],
                'value'       => $setting['value'],
                'default'     => $setting['value'],
                'group'       => $group,
                'offset'      => $groupOffsets[$group],
                'order'       => $groupOrders[$group],
                'type'        => $setting['type'],
                'options'     => $setting['options'],
                'description' => $setting['description'],
            ];

            $groupOffsets[$group]++;
            $groupOrders[$group]++;
        }

        Setting::upsert(
            $rows,
            uniqueBy: ['id'],
            update: ['key', 'name', 'group', 'offset', 'order', 'type', 'options', 'description', 'default'],
        );
    }

    /**
     * Check if any settings defined in this seeder are missing from the database.
     */
    public function settingsPending(): bool
    {
        foreach ($this->settings as $setting) {
            $id = Setting::formatKey($setting['key']);

            if (Setting::where('id', $id)->doesntExist()) {
                Log::info('Setting '.$id.' missing, update available');

                return true;
            }
        }

        return false;
    }
}
