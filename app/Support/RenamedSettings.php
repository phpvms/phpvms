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
    ];

    /**
     * The current key for a setting key; the key itself when it was never renamed.
     */
    public static function resolve(string $key): string
    {
        return self::MAP[strtolower($key)] ?? $key;
    }
}
