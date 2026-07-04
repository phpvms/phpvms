<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One booked fare class on a PIREP (name/code + passenger/cargo count). */
#[TypeScript]
final class PirepFareData extends Data
{
    public function __construct(
        public string $name,
        public ?string $code,
        public int $count,
    ) {}
}
