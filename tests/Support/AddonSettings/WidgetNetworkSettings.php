<?php

declare(strict_types=1);

namespace Tests\Support\AddonSettings;

use App\Contracts\Addons\AddonSettings;

/**
 * A second section of the test addon's settings.
 */
final class WidgetNetworkSettings extends AddonSettings
{
    public int $retries = 3;

    public string $endpoint = 'https://example.test';

    public static function section(): string
    {
        return 'network';
    }

    public static function sectionLabel(): string
    {
        return 'Network';
    }
}
