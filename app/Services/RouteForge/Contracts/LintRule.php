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
 * LintRunner; each rule owns one concern from the v1 catalog (L1–L11).
 */
interface LintRule
{
    /**
     * Stable identifier for the rule (e.g. "L1", "L2b", "L11").
     *
     * Mirrored verbatim in the TypeScript client-side lint output so reports
     * stay comparable across both implementations.
     */
    public function id(): string;

    /**
     * Default severity of the issues emitted by this rule.
     *
     * One of LintIssue::SEVERITY_ERROR, SEVERITY_WARNING, or SEVERITY_INFO.
     * A rule MAY override severity per emitted issue when an edge case warrants
     * it (e.g. a "warning" rule that escalates to "error" under specific
     * conditions), but the default returned here drives UI grouping.
     */
    public function severity(): string;

    /**
     * Run the rule against the given context.
     *
     * @return list<LintIssue> Zero or more issues. Order is preserved.
     */
    public function check(LintContext $ctx): array;
}
