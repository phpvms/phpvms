<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The catalog of OAuth scopes exposed by the API.
 *
 * Each case maps a functional area of the API to a scope string that can be
 * requested, granted, and enforced. The wildcard {@see self::All} grants full
 * access and is what legacy api_key authentication is treated as holding.
 *
 * This enum is the single source of truth: Passport scope registration
 * (App\Providers\PassportServiceProvider), the scope-enforcement middleware
 * (App\Http\Middleware\CheckApiScope), the Connections UI, and the Filament
 * admin all read from here.
 */
enum ApiScope: string
{
    case All = '*';
    case AirlinesRead = 'airlines:read';
    case AirportsRead = 'airports:read';
    case FleetRead = 'fleet:read';
    case FlightsRead = 'flights:read';
    case PirepsRead = 'pireps:read';
    case PirepsWrite = 'pireps:write';
    case UserRead = 'user:read';
    case BidsWrite = 'bids:write';
    case SettingsWrite = 'settings:write';

    /**
     * A human-readable description shown in the authorization screen and UIs.
     */
    public function description(): string
    {
        return match ($this) {
            self::All           => 'Full access to the entire API',
            self::AirlinesRead  => 'Read airlines',
            self::AirportsRead  => 'Read airports',
            self::FleetRead     => 'Read fleet and aircraft',
            self::FlightsRead   => 'Read flights, schedules and briefings',
            self::PirepsRead    => 'Read your PIREPs and their details',
            self::PirepsWrite   => 'File and update PIREPs, and send ACARS data',
            self::UserRead      => 'Read your profile, fleet and PIREPs',
            self::BidsWrite     => 'Create and remove flight bids',
            self::SettingsWrite => 'Update your account settings',
        };
    }

    /**
     * The scope catalog as a `scope => description` map, in the order shown to
     * users. The wildcard is intentionally excluded — it is an internal
     * full-access marker, not something users pick per-token.
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (self::selectable() as $scope) {
            $catalog[$scope->value] = $scope->description();
        }

        return $catalog;
    }

    /**
     * The scopes a user may select when minting a token (everything except the
     * wildcard).
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $scope): bool => $scope !== self::All,
        ));
    }

    /**
     * All scope identifiers, including the wildcard, for Passport registration.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }
}
