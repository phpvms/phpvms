<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Automatic fare pricing.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `fares`.
 */
final class FaresSettings extends Settings
{
    public bool $auto_price = false;

    public float $low_cost_multiplier = 0.8;

    #[Override]
    public static function group(): string
    {
        return 'fares';
    }
}
