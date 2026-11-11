<?php

declare(strict_types=1);

namespace App\Settings;

use App\Support\Settings\SettingField;
use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Scheduler identity.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `cron`.
 */
final class CronSettings extends Settings
{
    #[SettingField(hidden: true)]
    public string $random_id = '';

    #[Override]
    public static function group(): string
    {
        return 'cron';
    }
}
