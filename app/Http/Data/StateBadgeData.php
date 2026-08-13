<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A labelled + colored status badge (enum label + semantic color token). */
#[TypeScript]
final class StateBadgeData extends Data
{
    public function __construct(
        public string $label,
        public string $color,
    ) {}
}
