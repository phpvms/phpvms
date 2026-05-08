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

    #[\Deprecated('Use ->getLabel() on the enum instance instead')]
    public static function label(mixed $value): ?string
    {
        if ($value instanceof self) {
            return $value->getLabel();
        }

        $case = static::tryFrom($value);

        return $case ? $case->getLabel() : $value;
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
