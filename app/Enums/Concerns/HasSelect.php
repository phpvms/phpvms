<?php

namespace App\Enums\Concerns;

trait HasSelect
{
    public static function labels(): array
    {
        return collect(static::cases())
            ->mapWithKeys(fn ($case): array => [$case->value => $case->getLabel()])
            ->toArray();
    }

    #[\Deprecated('If you have an enum instance, call $enum->getLabel() directly. If you have a raw scalar value (e.g. from the DB or a request), use static::tryFrom($value)?->getLabel().')]
    public static function label(mixed $value): ?string
    {
        if ($value instanceof self) {
            return $value->getLabel();
        }

        return static::tryFrom($value)?->getLabel();
    }

    public static function select(bool $add_blank = false): array
    {
        $labels = static::labels();

        if ($add_blank) {
            return ['' => ''] + $labels;
        }

        return $labels;
    }
}
