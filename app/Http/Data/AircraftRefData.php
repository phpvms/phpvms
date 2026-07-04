<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Minimal aircraft reference for the dashboard last flight. */
#[TypeScript]
final class AircraftRefData extends Data
{
    public function __construct(
        public int $id,
        public ?string $registration,
        public ?string $name,
    ) {}
}
