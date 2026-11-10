<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingType;
use BackedEnum;
use DateTimeInterface;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Contracts\HasLabel;

/**
 * The form field editing one setting, chosen by the setting's type, shared by
 * the core and addon settings pages.
 */
final class SettingFieldFactory
{
    public static function make(SettingDefinition $definition): Field
    {
        $field = match (true) {
            $definition->key === 'units.currency' => Select::make($definition->key)
                ->options(self::currencies())
                ->searchable()
                ->required()
                ->native(false),
            default => self::forType($definition),
        };

        if ($definition->key === 'bids.expire_time' && $field instanceof TextInput) {
            $field->minValue(0);
        }

        return $field
            ->label($definition->label())
            ->inlineLabel()
            ->belowLabel($definition->description());
    }

    /**
     * The value a setting's field holds for a stored value.
     */
    public static function formValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum        => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default                             => $value,
        };
    }

    /**
     * @return array<string|int, string>
     */
    private static function enumOptions(SettingDefinition $definition): array
    {
        /** @var class-string<BackedEnum> $enum */
        $enum = $definition->enum;
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[$case->value] = $case instanceof HasLabel ? (string) $case->getLabel() : (string) $case->value;
        }

        return $options;
    }

    private static function forType(SettingDefinition $definition): Field
    {
        return match ($definition->type) {
            SettingType::Bool => Toggle::make($definition->key)
                ->offIcon(Phosphor::XLight)
                ->offColor('danger')
                ->onIcon(Phosphor::CheckLight)
                ->onColor('success'),
            SettingType::Int => TextInput::make($definition->key)
                ->integer()
                ->required(),
            SettingType::Float => TextInput::make($definition->key)
                ->numeric()
                ->step(0.01)
                ->required(),
            SettingType::Date => DatePicker::make($definition->key)
                ->format('Y-m-d')
                ->native(false),
            SettingType::Enum => Select::make($definition->key)
                ->options(self::enumOptions($definition))
                ->selectablePlaceholder(false)
                ->required()
                ->native(false),
            SettingType::String => TextInput::make($definition->key)
                ->string(),
        };
    }

    /**
     * Every supported currency, as "Name (symbol/CODE)" by code.
     *
     * @return array<string, string>
     */
    private static function currencies(): array
    {
        $currencies = [];

        foreach (config('money.currencies') as $currency => $attributes) {
            $currencies[$currency] = $attributes['name'].' ('.$attributes['symbol'].'/'.$currency.')';
        }

        return $currencies;
    }
}
