<?php

declare(strict_types=1);

use App\Addons\Support\AddonSettingsRegistry;
use App\Contracts\Addons\AddonSettings;
use App\Enums\WeightUnit;
use App\Settings\CaptchaSettings;
use App\Settings\UnitsSettings;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingType;
use Tests\Support\AddonSettings\WidgetNetworkSettings;
use Tests\Support\AddonSettings\WidgetSettings;

/**
 * How a settings class is read: each public property is one setting, typed by
 * its declaration and defaulted by its initial value, presented through
 * #[SettingField].
 */
final class UndefaultedAddonSettings extends AddonSettings
{
    public string $missing;
}

final class UntypedAddonSettings extends AddonSettings
{
    public array $list = [];
}

it('reads one definition per property, in declaration order, typed and defaulted', function (): void {
    AddonSettingsRegistry::register(app(), 'vendor/widget', [WidgetSettings::class]);

    $definitions = collect(SettingsRegistry::definitionsFor(WidgetSettings::class))->keyBy('name');

    expect($definitions->keys()->all())->toBe(['token', 'flag', 'count', 'ratio', 'day', 'unit', 'cursor'])
        ->and($definitions['flag']->type)->toBe(SettingType::Bool)
        ->and($definitions['count']->type)->toBe(SettingType::Int)
        ->and($definitions['ratio']->type)->toBe(SettingType::Float)
        ->and($definitions['day']->type)->toBe(SettingType::Date)
        ->and($definitions['day']->nullable)->toBeTrue()
        ->and($definitions['unit']->type)->toBe(SettingType::Enum)
        ->and($definitions['unit']->enum)->toBe(WeightUnit::class)
        ->and($definitions['unit']->default)->toBe(WeightUnit::POUNDS)
        ->and($definitions['cursor']->hidden)->toBeTrue()
        ->and($definitions['token']->label())->toBe('API token')
        ->and($definitions['count']->label())->toBe('Count');
});

it('groups an addon section under its registry_id and section', function (): void {
    AddonSettingsRegistry::register(app(), 'vendor/widget', [WidgetSettings::class, WidgetNetworkSettings::class]);

    expect(WidgetSettings::group())->toBe('vendor/widget:general')
        ->and(WidgetNetworkSettings::group())->toBe('vendor/widget:network')
        ->and(WidgetSettings::repository())->toBe('addons');
});

it('refuses the group of an addon class no addon registered', function (): void {
    expect(fn (): string => WidgetSettings::group())->toThrow(LogicException::class);
});

it('rejects a property without a default', function (): void {
    expect(fn () => AddonSettingsRegistry::register(app(), 'vendor/undefaulted', [UndefaultedAddonSettings::class]))
        ->toThrow(LogicException::class, 'must declare a default value');
});

it('rejects a property of an unsupported type', function (): void {
    expect(fn () => AddonSettingsRegistry::register(app(), 'vendor/untyped', [UntypedAddonSettings::class]))
        ->toThrow(LogicException::class, 'unsupported type');
});

it('rejects a class that is not an addon settings class', function (): void {
    expect(fn () => AddonSettingsRegistry::register(app(), 'vendor/wrong', [UnitsSettings::class]))
        ->toThrow(InvalidArgumentException::class);
});

it('labels core settings from the settings translations and marks secrets encrypted', function (): void {
    $definitions = collect(SettingsRegistry::definitionsFor(CaptchaSettings::class))->keyBy('name');

    expect($definitions['secret_key']->label())->toBe('hCaptcha Secret Key')
        ->and($definitions['secret_key']->description())->toBe('Your hCaptcha Secret Key')
        ->and($definitions['secret_key']->encrypted)->toBeTrue()
        ->and($definitions['site_key']->encrypted)->toBeFalse()
        ->and($definitions['site_key']->key)->toBe('captcha.site_key');
});
