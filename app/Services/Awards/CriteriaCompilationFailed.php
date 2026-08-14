<?php

declare(strict_types=1);

namespace App\Services\Awards;

use RuntimeException;

/**
 * A stored rule tree could not be compiled faithfully.
 *
 * Awards must fail closed. An unfiltered users query is the most permissive
 * result there is — every active user qualifies — so a tree we cannot compile
 * exactly has to stop the run rather than return a query missing criteria.
 * That is the opposite of the vendor filter's fail-safe, which drops criteria
 * so a table shows every row; harmless when listing, a mass grant here.
 */
class CriteriaCompilationFailed extends RuntimeException
{
    public static function exceedsBounds(int $maxRules, int $maxNestingDepth): self
    {
        return new self("Award criteria exceed the configured bounds (max {$maxRules} rules, max nesting depth {$maxNestingDepth}).");
    }

    public static function unknownConstraint(string $name): self
    {
        return new self("Award criteria reference the unregistered constraint [{$name}].");
    }
}
