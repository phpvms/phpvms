<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One type rating (name + type) for the profile page. */
#[TypeScript]
final class TypeRatingData extends Data
{
    public function __construct(
        public string $name,
        public string $type,
    ) {}
}
