<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Subfleet;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;

/**
 * L2 — Per-row range mismatch warning.
 *
 * Emits a warning for any row whose distance exceeds every selected subfleet's
 * `max_range_nm`. Subfleets with `max_range_nm IS NULL` are treated as
 * unrestricted (always compatible) — short-circuits the check and suppresses
 * the warning batch-wide for that row.
 */
final class L2RangeMismatch implements LintRule
{
    public function id(): string
    {
        return 'L2';
    }

    public function severity(): string
    {
        return LintIssue::SEVERITY_WARNING;
    }

    public function check(LintContext $ctx): array
    {
        // Short-circuit: an unrestricted subfleet covers every distance, so
        // no row can fail L2. Avoids the per-row scan entirely.
        $hasUnrestricted = $ctx->selectedSubfleets->contains(
            fn (Subfleet $subfleet): bool => $subfleet->max_range_nm === null,
        );

        if ($hasUnrestricted) {
            return [];
        }

        $maxRange = $ctx->selectedSubfleets
            ->pluck('max_range_nm')
            ->filter()
            ->max();

        if ($maxRange === null) {
            // No selected subfleets, or every selected one has NULL range and
            // we'd have short-circuited above. L3 covers the empty case.
            return [];
        }

        $issues = [];
        foreach ($ctx->rows as $index => $row) {
            $distance = (float) ($row['distance_nm'] ?? 0);

            if ($distance <= (float) $maxRange) {
                continue;
            }

            $issues[] = new LintIssue(
                ruleId: $this->id(),
                severity: $this->severity(),
                message: __('filament.routeforge.lint.l2_range_mismatch', [
                    'distance'  => (int) $distance,
                    'max_range' => (int) $maxRange,
                ]),
                rowIndex: $index,
                details: [
                    'distance_nm'        => $distance,
                    'max_subfleet_range' => (int) $maxRange,
                    'incompatible_count' => $ctx->selectedSubfleets->count(),
                ],
            );
        }

        return $issues;
    }
}
