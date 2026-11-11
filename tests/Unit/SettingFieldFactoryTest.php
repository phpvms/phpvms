<?php

declare(strict_types=1);

use App\Enums\WeightUnit;
use App\Filament\Support\SettingFieldFactory;
use App\Support\Settings\SettingsRegistry;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * The settings pages edit each setting with the field its type calls for.
 */
function settingField(string $key): Field
{
    return SettingFieldFactory::make(app(SettingsRegistry::class)->find($key));
}

it('picks the field from the setting type', function (string $key, string $field): void {
    expect(settingField($key))->toBeInstanceOf($field);
})->with([
    'bool'   => ['general.telemetry', Toggle::class],
    'int'    => ['pilots.id_length', TextInput::class],
    'float'  => ['fares.low_cost_multiplier', TextInput::class],
    'date'   => ['general.start_date', DatePicker::class],
    'enum'   => ['units.weight', Select::class],
    'string' => ['general.site_name', TextInput::class],
]);

it('keys the field on the setting key and labels it from the translations', function (): void {
    $field = settingField('general.site_name');

    expect($field->getName())->toBe('general.site_name')
        ->and($field->getLabel())->toBe('Site Name');
});

it('offers exactly the options of an option setting', function (): void {
    /** @var Select $field */
    $field = settingField('units.weight');

    expect($field->getOptions())->toBe(['lbs' => 'lbs', 'kg' => 'kg']);
});

it('makes the currency a searchable select of every supported currency', function (): void {
    /** @var Select $field */
    $field = settingField('units.currency');

    expect($field)->toBeInstanceOf(Select::class)
        ->and($field->isSearchable())->toBeTrue()
        ->and(array_keys($field->getOptions()))->toBe(array_keys(config('money.currencies')));
});

it('refuses a negative bid expiry on the field', function (): void {
    /** @var TextInput $field */
    $field = settingField('bids.expire_time');

    expect($field->getMinValue())->toBe(0);
});

it('converts stored values to field values', function (): void {
    expect(SettingFieldFactory::formValue(WeightUnit::KILOGRAMS))->toBe('kg')
        ->and(SettingFieldFactory::formValue(CarbonImmutable::parse('2024-06-01')))->toBe('2024-06-01')
        ->and(SettingFieldFactory::formValue(true))->toBeTrue()
        ->and(SettingFieldFactory::formValue(5))->toBe(5);
});
