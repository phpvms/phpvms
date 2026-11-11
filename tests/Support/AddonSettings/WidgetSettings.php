<?php

declare(strict_types=1);

namespace Tests\Support\AddonSettings;

use App\Contracts\Addons\AddonSettings;
use App\Enums\WeightUnit;
use App\Support\Settings\SettingField;
use Carbon\CarbonImmutable;

/**
 * The general section of a test addon's settings: one property per type.
 */
final class WidgetSettings extends AddonSettings
{
    #[SettingField(label: 'API token', description: 'The token the widget sends')]
    public string $token = '';

    public bool $flag = false;

    public int $count = 0;

    public float $ratio = 0.0;

    public ?CarbonImmutable $day = null;

    public WeightUnit $unit = WeightUnit::POUNDS;

    #[SettingField(hidden: true)]
    public string $cursor = 'abc';
}
