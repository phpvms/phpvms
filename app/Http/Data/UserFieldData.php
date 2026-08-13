<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A public custom user field (name/value) for the profile page. */
#[TypeScript]
final class UserFieldData extends Data
{
    public function __construct(
        public string $name,
        public ?string $value,
    ) {}
}
