<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Minimal rank reference (name) for SPA projections. */
#[TypeScript]
final class RankData extends Data
{
    public function __construct(
        public string $name,
    ) {}
}
