<?php

declare(strict_types=1);

namespace App\Contracts\Addons;

use App\Features\Addons\Support\AddonSettingsRegistry;
use App\Support\Settings\SettingField;
use Illuminate\Support\Str;
use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Base class of an addon's settings: one section of the addon's settings page.
 *
 * Declare each setting as a typed public property with a default, and describe
 * it with {@see SettingField}:
 *
 *     final class AwardsSettings extends AddonSettings
 *     {
 *         #[SettingField(label: 'Awards enabled')]
 *         public bool $enabled = true;
 *
 *         public int $max_awards = 10;
 *     }
 *
 * and list the class from {@see AddonServiceProvider::registerSettings()}. A
 * setting reads as its default until it is first saved; no migration is needed.
 * Read values with `addon_setting('alias', 'enabled')` or by resolving the class
 * from the container.
 *
 * Values live in the `addon_settings` table, under the group
 * `{registry_id}:{section}`, so they survive a reinstall and never collide with
 * core settings or another addon's.
 */
abstract class AddonSettings extends Settings
{
    /**
     * The section of the addon's settings page this class fills, and part of
     * its storage group. Changing it moves the settings to an empty group.
     */
    public static function section(): string
    {
        return 'general';
    }

    /**
     * The heading of the section on the addon's settings page.
     */
    public static function sectionLabel(): string
    {
        return Str::headline(static::section());
    }

    #[Override]
    final public static function group(): string
    {
        return AddonSettingsRegistry::registryIdOf(static::class).':'.static::section();
    }

    #[Override]
    final public static function repository(): string
    {
        return 'addons';
    }
}
