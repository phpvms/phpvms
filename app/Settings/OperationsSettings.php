<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Which aircraft and flights a pilot may use.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `operations`.
 */
final class OperationsSettings extends Settings
{
    public bool $restrict_aircraft_to_rank = false;

    public bool $restrict_aircraft_to_typerating = false;

    public bool $only_aircraft_at_dpt_airport = false;

    public bool $only_company_aircraft = false;

    public bool $block_aircraft = false;

    public bool $advanced_fuel = false;

    public bool $home_hubs_only = false;

    public bool $only_flights_from_current = false;

    public bool $only_show_flights_from_current = false;

    #[Override]
    public static function group(): string
    {
        return 'operations';
    }
}
