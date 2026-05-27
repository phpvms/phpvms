<?php

declare(strict_types=1);

namespace App\Services\RouteForge\Rules;

use App\Models\Subfleet;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Enums\LintSeverity;
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
    public const string ID = 'L2';

    public const LintSeverity SEVERITY = LintSeverity::Warning;

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

        // Filter nulls only — Collection::filter() with no callback also drops
        // `0`, but max_range_nm === 0 is a legitimate "grounded" subfleet whose
        // row contributes to maxRange selection logic.
        $maxRange = $ctx->selectedSubfleets
            ->pluck('max_range_nm')
            ->filter(static fn ($value): bool => $value !== null)
            ->max();

        if ($maxRange === null) {
            // No selected subfleets, or every selected one has NULL range and
            // we'd have short-circuited above. L3 covers the empty case.
            return [];
        }

        $issues = [];
        foreach ($ctx->rows as $row) {
            $distance = $row->distanceNm;

            if ($distance <= (float) $maxRange) {
                continue;
            }

            $issues[] = new LintIssue(
                ruleId: self::ID,
                severity: self::SEVERITY,
                message: __('filament.routeforge.lint.l2_range_mismatch', [
                    'distance'  => (int) $distance,
                    'max_range' => (int) $maxRange,
                ]),
                rowIndex: $row->index,
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
