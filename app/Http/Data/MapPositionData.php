<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A live flight's current position, for a map marker. */
#[TypeScript]
final class MapPositionData extends Data
{
    public function __construct(
        public float $lat,
        public float $lon,
        public float $altitude,
        public float $heading,
    ) {}
}
