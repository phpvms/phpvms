<?php

declare(strict_types=1);

namespace App\Http\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One `<select>` option, in the shape Nuxt UI's USelect/USelectMenu expect
 * (`{ value, label }`). The app's option sources are all keyed maps —
 * `Airline::selectList()` is id => name, `Countries::getSelectList()` is
 * code => name — so `fromMap()` flips them into the list form.
 */
#[TypeScript]
final class SelectOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}

    /**
     * @param  array<array-key, string>|Collection<array-key, string> $map keyed value => label
     * @return list<self>
     */
    public static function fromMap(array|Collection $map): array
    {
        return collect($map)
            ->map(fn (string $label, string|int $value): self => new self(
                value: (string) $value,
                label: $label,
            ))
            ->values()
            ->all();
    }
}
