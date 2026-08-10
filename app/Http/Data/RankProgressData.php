<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Rank progress tape: current rank -> next, with percent (0-100). */
#[TypeScript]
final class RankProgressData extends Data
{
    public function __construct(
        public string $from,
        public ?string $to,
        public int $pct,
        public float $currentHours,
        public ?float $targetHours,
        public ?float $hoursRemaining,
    ) {}
}
