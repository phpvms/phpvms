<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Maps setting keys that were renamed or moved to a different group onto
 * their current key.
 *
 * `SettingService` and the `setting()` helper resolve every key through here,
 * so asking for an old key returns the new key's value and saving through an
 * old key writes the new row. Addons, themes and API clients that still use
 * the old spelling keep working.
 *
 * Entries are permanent. Never remove one, or code on the old key starts
 * throwing SettingNotFound. When adding an entry, add a data migration in
 * `database/migrations_data/` that moves the existing row to the new key.
 */
final class RenamedSettings
{
    /** @var array<string, string> old key => new key */
    public const array MAP = [
        'general.auto_airport_lookup'    => 'airports.auto_airport_lookup',
        'general.allow_unadded_airports' => 'airports.allow_unadded_airports',

        'pireps.restrict_aircraft_to_rank'       => 'operations.restrict_aircraft_to_rank',
        'pireps.restrict_aircraft_to_typerating' => 'operations.restrict_aircraft_to_typerating',
        'pireps.only_aircraft_at_dpt_airport'    => 'operations.only_aircraft_at_dpt_airport',
        'bids.block_aircraft'                    => 'operations.block_aircraft',
        'simbrief.block_aircraft'                => 'operations.block_aircraft',
        'pireps.advanced_fuel'                   => 'operations.advanced_fuel',
        'flights.only_company_aircraft'          => 'operations.only_company_aircraft',

        'pilots.home_hubs_only'                 => 'operations.home_hubs_only',
        'pilots.only_flights_from_current'      => 'operations.only_flights_from_current',
        'pilots.only_show_flights_from_current' => 'operations.only_show_flights_from_current',
    ];

    /**
     * The current key for a setting key; the key itself when it was never renamed.
     */
    public static function resolve(string $key): string
    {
        return self::MAP[strtolower($key)] ?? $key;
    }
}
