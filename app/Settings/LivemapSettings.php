<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * How the live map shows flights in progress.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `livemap`.
 */
final class LivemapSettings extends Settings
{
    public int $live_time = 30;

    public int $idle_time = 60;

    public string $center_coords = '30.1945,-97.6699';

    public int $default_zoom = 5;

    public int $update_interval = 60;

    #[Override]
    public static function group(): string
    {
        return 'livemap';
    }
}
