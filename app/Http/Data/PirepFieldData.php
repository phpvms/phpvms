<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A custom PIREP field (name/value) for the detail view. */
#[TypeScript]
final class PirepFieldData extends Data
{
    public function __construct(
        public string $name,
        public ?string $value,
    ) {}
}
