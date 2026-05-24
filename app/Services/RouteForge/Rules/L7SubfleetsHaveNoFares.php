<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Subfleet;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L7 — Selected subfleets carry no fares (warning).
 *
 * Fires once batch-wide when every selected subfleet has zero rows in the
 * subfleet_fare pivot table. A bundle with no fares is commitable but the
 * generated flights inherit nothing pricewise — pilots see free flights or
 * NULL pricing depending on downstream behavior. The lint surfaces the
 * symptom before commit so the admin can attach fares to the subfleets
 * first.
 */
final class L7SubfleetsHaveNoFares implements LintRule
{
    public function id(): string
    {
        return 'L7';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

    public function check(LintContext $ctx): array
    {
        if ($ctx->selectedSubfleets->isEmpty()) {
            // L3 already covers "no subfleets selected"; don't double-warn.
            return [];
        }

        $anyHasFares = $ctx->selectedSubfleets->contains(
            fn (Subfleet $subfleet): bool => $subfleet->fares->isNotEmpty(),
        );

        if ($anyHasFares) {
            return [];
        }

        return [
            new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l7_no_fares'),
                rowIndex: null,
                details: [
                    'subfleet_count' => $ctx->selectedSubfleets->count(),
                ],
            ),
        ];
    }
}
