<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Limits on tours.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `tours`.
 */
final class ToursSettings extends Settings
{
    public int $max_in_progress = 1;

    #[Override]
    public static function group(): string
    {
        return 'tours';
    }
}
