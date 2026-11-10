<?php

declare(strict_types=1);

namespace Tests\Support\AddonSettings;

use App\Contracts\Addons\AddonSettings;
use App\Support\Settings\SettingField;

/**
 * An addon section with nothing to show on the settings page.
 */
final class HiddenOnlySettings extends AddonSettings
{
    #[SettingField(hidden: true)]
    public string $state = '';
}
