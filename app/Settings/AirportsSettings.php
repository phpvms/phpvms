<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Airport lookup and the default airport costs.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `airports`.
 */
final class AirportsSettings extends Settings
{
    public bool $auto_airport_lookup = true;

    public bool $allow_unadded_airports = false;

    public int $default_ground_handling_cost = 250;

    public float $default_jet_a_fuel_cost = 0.7;

    public float $default_100ll_fuel_cost = 0.9;

    public float $default_mogas_fuel_cost = 0.8;

    #[Override]
    public static function group(): string
    {
        return 'airports';
    }
}
