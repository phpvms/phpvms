<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One personal access token on the profile's API connections drawer. */
#[TypeScript]
final class ApiTokenData extends Data
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $id,
        public string $name,
        public array $scopes,
        public ?string $createdAt,
        public ?string $expiresAt,
    ) {}
}
