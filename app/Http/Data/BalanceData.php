<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Journal balance: raw amount + formatted currency string. */
#[TypeScript]
final class BalanceData extends Data
{
    public function __construct(
        public float $amount,
        public string $formatted,
    ) {}
}
