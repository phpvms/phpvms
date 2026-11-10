<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Map tile configuration shared by every map.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `map`.
 */
final class MapSettings extends Settings
{
    public string $custom_style_api_key = '';

    #[Override]
    public static function group(): string
    {
        return 'map';
    }
}
