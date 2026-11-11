<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * PIREP filing and clean-up.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `pireps`.
 */
final class PirepsSettings extends Settings
{
    public bool $disable_manual = false;

    public int $duplicate_check_time = 10;

    public int $tombstone_time = 12;

    public int $delete_cancelled_hours = 12;

    public int $delete_rejected_hours = 12;

    public bool $handle_diversion = true;

    #[Override]
    public static function group(): string
    {
        return 'pireps';
    }
}
