<?php

namespace App\Enums\Concerns;

use Deprecated;

trait HasSelect
{
    #[Deprecated('Use static::cases() directly with collections or map across the Enum cases.')]
    public static function labels(): array
    {
        return collect(static::cases())
            ->mapWithKeys(fn ($case): array => [$case->value => $case->getLabel()])
            ->toArray();
    }

    #[Deprecated('If you have an enum instance, call $enum->getLabel() directly. If you have a raw scalar value (e.g. from the DB or a request), use static::tryFrom($value)?->getLabel().')]
    public static function label(mixed $value): ?string
    {
        if ($value instanceof self) {
            return $value->getLabel();
        }

        return static::tryFrom($value)?->getLabel();
    }

    #[Deprecated('This method couples Enum logic with UI/Form concerns. Generate select options at the Form level from static::cases()')]
    public static function select(bool $add_blank = false): array
    {
        $labels = static::labels();

        if ($add_blank) {
            return ['' => ''] + $labels;
        }

        return $labels;
    }
}
