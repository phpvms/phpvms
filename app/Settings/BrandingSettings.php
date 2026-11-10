<?php

declare(strict_types=1);

namespace App\Settings;

use App\Support\Settings\SettingField;
use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Branding values that are not files (images live in assets).
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `branding`.
 */
final class BrandingSettings extends Settings
{
    #[SettingField(hidden: true)]
    public string $brand_color = '';

    #[Override]
    public static function group(): string
    {
        return 'branding';
    }
}
