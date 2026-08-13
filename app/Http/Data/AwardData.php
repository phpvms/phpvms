<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One pilot award for the profile page. */
#[TypeScript]
final class AwardData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $image,
    ) {}
}
