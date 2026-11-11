<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Pilot IDs, registration and activity.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `pilots`.
 */
final class PilotsSettings extends Settings
{
    public int $id_length = 4;

    public string $id_code = '';

    public bool $id_range_enabled = false;

    public int $id_range_start = 1;

    public int $id_range_end = 9999;

    public bool $id_fill_gaps = false;

    public bool $id_reuse_deleted = false;

    public bool $auto_accept = true;

    public int $auto_leave_days = 30;

    public bool $hide_inactive = true;

    public bool $restrict_to_company = false;

    public bool $allow_transfer_hours = true;

    public bool $count_transfer_hours = false;

    #[Override]
    public static function group(): string
    {
        return 'pilots';
    }
}
