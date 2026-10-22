<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Minimal pilot reference for map projections. */
#[TypeScript]
final class MapPilotRefData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public string $ident,
    ) {}
}
