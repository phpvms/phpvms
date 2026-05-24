<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L3 — Empty subfleet selection warning.
 *
 * Fires once batch-wide when the form's selected subfleet list is empty.
 * A batch with no subfleets is technically commitable (flights can be added
 * later) but operationally meaningless — no aircraft can fly the routes.
 */
final class L3EmptySubfleets implements LintRule
{
    public function id(): string
    {
        return 'L3';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

    public function check(LintContext $ctx): array
    {
        if ($ctx->selectedSubfleets->isNotEmpty()) {
            return [];
        }

        return [
            new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l3_empty_subfleets'),
                rowIndex: null,
                details: [],
            ),
        ];
    }
}
