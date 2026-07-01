<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Services\AddonSettingService;
use App\Services\SettingService;
use Illuminate\Support\Carbon;

/**
 * Shared typed-casting for settings stored as strings.
 *
 * Used by both {@see SettingService} (core `settings`) and
 * {@see AddonSettingService} (`addon_settings`) so the two
 * settings systems cast identically.
 */
trait CastsSettingValue
{
    /**
     * Cast a raw stored string value to its typed PHP representation.
     */
    protected function castSettingValue(?string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool', 'boolean'          => in_array($value, ['true', '1', 1], true),
            'date'                     => Carbon::parse($value),
            'int', 'integer', 'number' => (int) $value,
            'float'                    => (float) $value,
            default                    => $value,
        };
    }
}
