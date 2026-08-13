<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** PIREP state badge (int value + label + semantic color). */
#[TypeScript]
final class PirepStateData extends Data
{
    public function __construct(
        public int $value,
        public string $label,
        public string $color,
    ) {}
}
