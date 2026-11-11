<?php

declare(strict_types=1);

namespace App\Settings;

use App\Support\Settings\SettingField;
use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Identity of this install on the phpVMS addon registry.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `registry`.
 */
final class RegistrySettings extends Settings
{
    #[SettingField(hidden: true)]
    public string $va_global_id = '';

    #[SettingField(hidden: true)]
    public string $public_key = '+SDNBf6LCATJElc5yi2mrhpJwGh5So/s1Hi3jCUETic=';

    #[Override]
    public static function group(): string
    {
        return 'registry';
    }
}
