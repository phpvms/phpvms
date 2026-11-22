<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use Closure;

final readonly class StepContext
{
    /**
     * @param Closure(string): void            $log
     * @param Closure(int, int, ?string): void $progress
     */
    public function __construct(
        public bool $fresh,
        public Closure $log,
        public Closure $progress,
    ) {}
}
