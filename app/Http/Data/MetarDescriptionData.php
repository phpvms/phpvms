<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;

final class MetarDescriptionData extends Data
{
    public function __construct(
        /** Meteocons icon name. */
        public readonly string $icon,
        public readonly string $sentence,
        public readonly ?string $category,
    ) {}
}
