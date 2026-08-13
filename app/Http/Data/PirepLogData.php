<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One ACARS log line on a PIREP (timestamp + message). */
#[TypeScript]
final class PirepLogData extends Data
{
    public function __construct(
        public ?string $time,
        public string $message,
    ) {}
}
