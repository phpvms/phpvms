<?php

declare(strict_types=1);

namespace Tests\Support\AddonSettings;

use App\Contracts\Addons\AddonSettings;

/**
 * Declares the same setting name as {@see BetaSettings}, for another addon.
 */
final class AlphaSettings extends AddonSettings
{
    public string $api_token = '';
}
