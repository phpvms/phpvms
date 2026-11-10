<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Default passenger and cargo load factors.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `flights`.
 */
final class FlightsSettings extends Settings
{
    public int $default_load_factor = 82;

    public int $load_factor_variance = 5;

    public bool $use_cargo_load_factor = false;

    public int $default_cargo_load_factor = 32;

    public int $cargo_load_factor_variance = 5;

    #[Override]
    public static function group(): string
    {
        return 'flights';
    }
}
