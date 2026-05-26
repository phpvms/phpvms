<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Contracts;

use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * Contract for a single RouteForge lint rule.
 *
 * Each rule is a focused, side-effect-free check that inspects a LintContext
 * (form + rows + selected subfleets + airline + event + airline stats) and
 * returns zero or more LintIssue values. Rules are wired together by the
 * LintRunner; each rule owns one concern from the v1 catalog (L1–L12).
 *
 * Rule metadata (rule id, default severity) lives on each concrete class as
 * typed constants `public const string ID` and
 * `public const \App\Services\RouteForge\Enums\LintSeverity SEVERITY`.
 * Callers that need them read the constants directly (e.g.
 * `L4DuplicateFlightNumbersInBatch::ID`) — there is no `id()` / `severity()`
 * method on the interface because both values are static data with no logic
 * to test.
 */
interface LintRule
{
    /**
     * Run the rule against the given context.
     *
     * @return list<LintIssue> Zero or more issues. Order is preserved.
     */
    public function check(LintContext $ctx): array;
}
